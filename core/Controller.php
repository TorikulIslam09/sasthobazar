<?php
/**
 * Base controller.
 */
declare(strict_types=1);

abstract class Controller
{
    protected Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    protected function view(string $view, array $data = [], ?string $layout = 'main'): void
    {
        View::render($view, $data, $layout);
    }

    protected function json(mixed $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Extra permission check inside an action (route middleware is the first line of defence). */
    protected function authorize(string $permission): void
    {
        Auth::requirePermission($permission);
    }

    protected function notFound(): void
    {
        http_response_code(404);
        View::render('errors/404', ['title' => '404'], 'blank');
        exit;
    }

    /** Scalar-only copy of POST data (arrays dropped unless listed in $arrayKeys). */
    protected function postData(array $arrayKeys = []): array
    {
        $out = [];
        foreach ($_POST as $k => $v) {
            if (is_scalar($v)) {
                $out[$k] = trim((string) $v);
            } elseif (is_array($v) && in_array($k, $arrayKeys, true)) {
                $out[$k] = array_values(array_filter($v, 'is_scalar'));
            }
        }
        return $out;
    }

    /**
     * Minimal validator.
     * Rules: required, email, phone, min:N, max:N (string length), numeric, confirmed, in:a,b,
     *        integer, decimal (money: up to 10 digits + 2 decimals), gte:N, lte:N (numeric value),
     *        url (http/https), slug (letters, digits, hyphens; Bangla allowed), date (Y-m-d)
     * @return array field => error message
     */
    protected function validate(array $input, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleString) {
            $value = $input[$field] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            foreach (explode('|', $ruleString) as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $empty = $value === null || $value === '';
                $error = match ($name) {
                    'required'  => $empty ? __('v_required') : null,
                    'email'     => !$empty && !filter_var($value, FILTER_VALIDATE_EMAIL) ? __('v_email') : null,
                    'phone'     => !$empty && !Auth::isValidBdPhone((string) $value) ? __('v_phone') : null,
                    'min'       => !$empty && mb_strlen((string) $value) < (int) $arg ? __('v_min', ['n' => $arg]) : null,
                    'max'       => !$empty && mb_strlen((string) $value) > (int) $arg ? __('v_max', ['n' => $arg]) : null,
                    'numeric'   => !$empty && !is_numeric($value) ? __('v_numeric') : null,
                    'confirmed' => ($value !== ($input[$field . '_confirmation'] ?? null)) ? __('v_confirmed') : null,
                    'in'        => !$empty && !in_array((string) $value, explode(',', (string) $arg), true) ? __('v_in') : null,
                    'integer'   => !$empty && !preg_match('/^-?\d{1,10}$/', (string) $value) ? __('v_integer') : null,
                    'decimal'   => !$empty && !preg_match('/^\d{1,10}(\.\d{1,2})?$/', (string) $value) ? __('v_decimal') : null,
                    'gte'       => !$empty && is_numeric($value) && (float) $value < (float) $arg ? __('v_gte', ['n' => $arg]) : null,
                    'lte'       => !$empty && is_numeric($value) && (float) $value > (float) $arg ? __('v_lte', ['n' => $arg]) : null,
                    'url'       => !$empty && (!filter_var($value, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', (string) $value)) ? __('v_url') : null,
                    'slug'      => !$empty && !preg_match('/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u', (string) $value) ? __('v_slug') : null,
                    'date'      => !$empty && (!($d = DateTime::createFromFormat('!Y-m-d', (string) $value)) || $d->format('Y-m-d') !== (string) $value) ? __('v_date') : null,
                    default     => null,
                };
                if ($error !== null) {
                    $errors[$field] = $error;
                    break;
                }
            }
        }
        return $errors;
    }
}
