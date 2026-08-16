<?php

declare(strict_types=1);

namespace Govyx\Controllers;

use Govyx\Core\App;
use Govyx\Core\Request;
use Govyx\Core\Response;
use Govyx\Models\TaskModel;
use Govyx\Security\Audit;

class EvidenceController extends BaseApiController
{
    private const ALLOWED = [
        'pdf'  => 'application/pdf',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'txt'  => 'text/plain',
        'csv'  => 'text/csv',
    ];

    public function list(array $params): never
    {
        $this->requirePermission('VIEW_TASK');
        $task = TaskModel::findWithScope((int) $params['task_id'], $this->scopeOrgIds());
        if ($task === null) {
            Response::notFound('Task not found.');
        }
        $rows = App::db()->all(
            "SELECT e.*, u.full_name AS uploaded_by_name FROM evidence e JOIN users u ON u.id = e.user_id
              WHERE e.task_id = ? ORDER BY e.id DESC",
            [$task['id']]
        );
        Response::ok('Evidence retrieved.', ['evidence' => $rows]);
    }

    public function upload(array $params): never
    {
        $this->requirePermission('EDIT_TASK');
        $task = TaskModel::findWithScope((int) $params['task_id'], $this->scopeOrgIds());
        if ($task === null) {
            Response::notFound('Task not found.');
        }
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::error('Invalid file upload.', 422);
        }
        $file = $_FILES['file'];
        if ($file['size'] > 20 * 1024 * 1024) {
            Response::error('File exceeds 20 MB limit.', 422);
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$ext])) {
            Response::error('File type not allowed: .' . $ext, 422);
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($file['tmp_name']);
        if (!in_array($detected, array_values(self::ALLOWED), true)) {
            Response::error('File content does not match an allowed type.', 422);
        }

        $dir = App::config('storage.evidence') . '/' . (int) $task['id'];
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $name = bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            Response::error('Could not store the file.', 500);
        }
        $path = 'evidence/' . (int) $task['id'] . '/' . $name;
        $id = App::db()->insert('evidence', [
            'task_id'   => (int) $task['id'],
            'user_id'   => (int) $this->user['id'],
            'file_name' => basename($file['name']),
            'file_path' => $path,
            'file_type' => $detected,
            'file_size' => (int) $file['size'],
            'checksum'  => hash_file('sha256', $dir . '/' . $name),
            'version'   => 1,
        ]);
        Audit::log($this->user['id'], 'UPLOAD_EVIDENCE', 'task', $task['id'], ['evidence_id' => $id, 'file' => $file['name']]);
        Response::ok('Evidence uploaded.', ['evidence_id' => $id, 'checksum' => hash_file('sha256', $dir . '/' . $name)]);
    }

    public function download(array $params): never
    {
        $this->requirePermission('VIEW_TASK');
        $task = TaskModel::findWithScope((int) $params['task_id'], $this->scopeOrgIds());
        if ($task === null) {
            Response::notFound('Task not found.');
        }
        $row = App::db()->one('SELECT * FROM evidence WHERE id = ? AND task_id = ?', [(int) $params['id'], $task['id']]);
        if ($row === null) {
            Response::notFound('Evidence not found.');
        }
        $abs = App::config('storage.root') . '/' . $row['file_path'];
        if (!is_file($abs)) {
            Response::error('File missing on disk.', 404);
        }

        $checksum = hash_file('sha256', $abs);
        if (!hash_equals((string) $row['checksum'], $checksum)) {
            Audit::log($this->user['id'], 'EVIDENCE_TAMPERED', 'task', $task['id'], ['evidence_id' => (int) $row['id']]);
            Response::error('File integrity check failed.', 409);
        }

        $safe = str_replace(['"', "\r", "\n", ';'], '', (string) $row['file_name']);
        header('Content-Type: ' . $row['file_type']);
        header('Content-Length: ' . (int) $row['file_size']);
        header('Content-Disposition: attachment; filename="' . $safe . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, private');
        Audit::log($this->user['id'], 'DOWNLOAD_EVIDENCE', 'task', $task['id'], ['evidence_id' => (int) $row['id']]);
        readfile($abs);
        exit;
    }
}