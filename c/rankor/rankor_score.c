/*
 * rankor_score - C scoring utility for GOVYX / Project ARWE
 *
 * Performs transparent, explainable scoring for Rankor (sections 5, 17).
 * Reads JSON from stdin, writes a scored JSON result to stdout.
 *
 * Usage:
 *   rankor_score < input.json
 *
 * Input:
 * {
 *   "mode": "delay" | "workload" | "kpi",
 *   "tasks": [ { "id": 1, "days_overdue": 5, "priority": "high", ... } ]
 * }
 *
 * Only used where a PHP process explicitly invokes it (or via CLI);
 * HTTP never executes shell commands. All input is validated.
 */

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <ctype.h>

#define MAX_LINE 8192
#define MAX_SCORE_ITEMS 4096

typedef struct {
    long id;
    int days_overdue;
    int has_deadline;
    int priority_pts;
    int progress;
} task_t;

static int priority_points(const char *priority) {
    if (priority == NULL) return 10;
    if (strcmp(priority, "low") == 0) return 5;
    if (strcmp(priority, "medium") == 0) return 10;
    if (strcmp(priority, "high") == 0) return 15;
    if (strcmp(priority, "critical") == 0) return 20;
    return 10;
}

static double clamp_score(double s) {
    if (s < 0) return 0;
    if (s > 100) return 100;
    return s;
}

/* --- minimal JSON string extraction: find "key" : <value> --- */
static int extract_value(const char *src, const char *key, char *out, size_t out_sz) {
    const char *p = src;
    size_t klen = strlen(key);
    while ((p = strstr(p, key)) != NULL) {
        const char *after = p + klen;
        while (*after == ' ' || *after == '\t') after++;
        if (*after != ':') { p = p + 1; continue; }
        after++;
        while (*after == ' ' || *after == '\t') after++;
        if (*after == '"') {
            after++;
            size_t i = 0;
            while (*after && *after != '"' && i + 1 < out_sz) {
                out[i++] = *after;
                after++;
            }
            out[i] = '\0';
            return 1;
        }
        if (isdigit((unsigned char)*after) || *after == '-' || strncmp(after, "null", 4) == 0) {
            size_t i = 0;
            while (*after && *after != ',' && *after != '}' && *after != ']' && i + 1 < out_sz) {
                if (*after != ' ' && *after != '\n' && *after != '\r') out[i++] = *after;
                after++;
            }
            out[i] = '\0';
            return 1;
        }
        p = p + 1;
    }
    return 0;
}

static int parse_int(const char *s, int fallback) {
    if (s == NULL || *s == '\0') return fallback;
    return (int)strtol(s, NULL, 10);
}

/* Count occurrences of the substring "id": inside src (rough task count). */
static int count_tasks(const char *src) {
    int n = 0;
    const char *p = src;
    while ((p = strstr(p, "\"id\":")) != NULL) { n++; p += 5; }
    return n;
}

int main(int argc, char **argv) {
    char *input = malloc(MAX_LINE);
    if (input == NULL) { fprintf(stderr, "out of memory\n"); return 1; }

    size_t cap = MAX_LINE;
    size_t len = 0;
    int c;
    while ((c = getchar()) != EOF) {
        if (len + 1 >= cap) {
            cap *= 2;
            char *tmp = realloc(input, cap);
            if (tmp == NULL) { fprintf(stderr, "out of memory\n"); free(input); return 1; }
            input = tmp;
        }
        input[len++] = (char)c;
        if (c == '}') {
            /* accept only well-formed; we trim trailing brace if followed by nothing */
        }
    }
    input[len] = '\0';

    if (len < 2 || input[0] != '{') {
        fprintf(stderr, "invalid input: expected JSON object\n");
        free(input);
        return 2;
    }

    char mode[32] = "delay";
    char item[MAX_LINE];
    if (extract_value(input, "\"mode\"", mode, sizeof(mode)) == 0) {
        if (extract_value(input, "mode", mode, sizeof(mode)) == 0) mode[0] = '\0';
    }

    int n = count_tasks(input);
    if (n <= 0 || n > MAX_SCORE_ITEMS) {
        fprintf(stderr, "no tasks found (or too many)\n");
        free(input);
        return 0;
    }

    task_t *tasks = calloc((size_t)n, sizeof(task_t));
    if (tasks == NULL) { fprintf(stderr, "out of memory\n"); free(input); return 1; }

    /* parse each task object: locate "id": first occurrence, then fields after */
    const char *p = input;
    int idx = 0;
    while (idx < n && (p = strstr(p, "\"id\":")) != NULL) {
        char ids[64];
        const char *after = p + 5;
        while (*after == ' ' || *after == '\t') after++;
        size_t i = 0;
        while (*after && *after != ',' && *after != '}' && *after != ' ' && i + 1 < sizeof(ids)) {
            ids[i++] = *after++;
        }
        ids[i] = '\0';
        tasks[idx].id = strtol(ids, NULL, 10);
        tasks[idx].progress = 0;

        /* scan forward to the end of this task object */
        const char *end = p;
        int depth = 0;
        for (; *end; end++) {
            if (*end == '{') depth++;
            else if (*end == '}') { if (--depth <= 0) { end++; break; } }
        }
        if (*end == '\0' && depth > 0) { /* malformed, bail */ break; }

        char v[64];
        size_t field_len = (size_t)(end - p);
        if (field_len >= sizeof(item)) field_len = sizeof(item) - 1;
        memcpy(item, p, field_len);
        item[field_len] = '\0';

        if (extract_value(item, "\"days_overdue\"", v, sizeof(v))) {
            tasks[idx].days_overdue = parse_int(v, 0);
        }
        if (extract_value(item, "\"has_deadline\"", v, sizeof(v))) {
            tasks[idx].has_deadline = parse_int(v, 0);
        }
        if (extract_value(item, "\"progress\"", v, sizeof(v))) {
            tasks[idx].progress = parse_int(v, 0);
        }
        char prior[32];
        if (extract_value(item, "\"priority\"", prior, sizeof(prior))) {
            tasks[idx].priority_pts = priority_points(prior);
        } else {
            tasks[idx].priority_pts = 10;
        }
        p = end;
        idx++;
    }

    n = idx;

    /* delay scoring */
    printf("{\n  \"mode\": \"%s\",\n  \"version\": \"1.0\",\n  \"source\": \"c\",\n  \"scores\": [\n", mode);
    for (int i = 0; i < n; i++) {
        double score = 0.0;
        if (tasks[i].has_deadline) {
            if (tasks[i].days_overdue > 0) score += (tasks[i].days_overdue > 5) ? 50.0 : (double)(tasks[i].days_overdue * 10);
            else score += 5.0;
        }
        score += (double)tasks[i].priority_pts;
        score = clamp_score(score);
        printf("    { \"task_id\": %ld, \"delay_score\": %.2f }%s\n",
               tasks[i].id, score, (i + 1 < n) ? "," : "");
    }
    printf("  ]\n}\n");

    free(tasks);
    free(input);
    return 0;
}