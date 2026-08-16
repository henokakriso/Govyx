<?php

declare(strict_types=1);

namespace Govyx\Controllers;

use Govyx\Core\Response;
use Govyx\Security\Auth;
use Govyx\Security\Csrf;
use Govyx\Security\Permission;
use Govyx\Security\Scope;
use Govyx\Validators\Validator;

abstract class BaseApiController
{
    protected array $user;

    public function __construct()
    {
        $this->user = Auth::requireAuth();
        if (!in_array(\Govyx\Core\Request::method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            Csrf::protect();
        }
    }

    protected function requirePermission(string $permission): void
    {
        Permission::require($this->user, $permission);
    }

    /** Org ids in the current user's administrative scope. */
    protected function scopeOrgIds(): array
    {
        return Scope::organizationIds((int) $this->user['organization_id'], (string) $this->user['role_code']);
    }

    protected function scopeDeptIds(): array
    {
        return Scope::departmentIds(
            (int) $this->user['organization_id'],
            (string) $this->user['role_code'],
            isset($this->user['department_id']) ? (int) $this->user['department_id'] : null
        );
    }

    /**
     * Validate request input.
     * $rules = ['field' => ['required', 'string', 'in:a,b,c', ...]]
     * Fails with 422 + field errors when invalid.
     */
    protected function validate(array $rules): void
    {
        $v = new Validator();
        foreach ($rules as $field => $fieldRules) {
            $value = \Govyx\Core\Request::input($field);
            $label = ucfirst(str_replace('_', ' ', $field));
            foreach ($fieldRules as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                switch ($name) {
                    case 'required': $v->required($value, $field, $label); break;
                    case 'string':   $v->string($value, $field, $label, $arg === null ? 255 : (int) $arg); break;
                    case 'int':      $v->int($value, $field, $label, $arg === null ? -1 : (int) $arg); break;
                    case 'in':       $v->in($value, $arg === null ? [] : array_map('trim', explode(',', $arg)), $field, $label); break;
                    case 'date':     $v->date($value, $field, $label); break;
                    case 'email':    $v->email($value, $field, $label); break;
                    case 'numeric':  $v->numeric($value, $field, $label); break;
                    default:
                        if (str_starts_with($name, 'exists:')) {
                            $v->exists($value, $field, $label, $name);
                        }
                }
            }
        }
        if ($v->fails()) {
            Response::error('Validation failed', 422, $v->errors());
        }
    }
}