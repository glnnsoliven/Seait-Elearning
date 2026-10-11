<?php
/**
 * SEAIT E-Learning — Base Controller
 *
 * All role-specific controllers extend this class.
 * Provides shared rendering, redirection, and data helpers.
 */
abstract class BaseController
{
    protected string $pageTitle = '';
    protected array  $data      = [];

    /**
     * Pass data to the view and render it.
     */
    protected function view(string $template, array $data = []): void
    {
        extract(array_merge($this->data, $data));
        $viewPath = ROOT_PATH . '/resources/views/' . $template . '.php';
        if (!file_exists($viewPath)) {
            http_response_code(404);
            include ROOT_PATH . '/includes/404.php';
            exit;
        }
        include $viewPath;
    }

    /**
     * Redirect to a URL.
     */
    protected function redirect(string $url): never
    {
        header("Location: {$url}");
        exit;
    }

    /**
     * Return JSON response.
     */
    protected function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Require the current user to have a specific role.
     */
    protected function requireRole(string|array $roles): void
    {
        Auth::requireRole($roles);
    }

    /**
     * Get validated POST string.
     */
    protected function input(string $key, string $default = ''): string
    {
        return postStr($key, $default);
    }

    /**
     * Get validated POST integer.
     */
    protected function inputInt(string $key, int $default = 0): int
    {
        return postInt($key, $default);
    }

    /**
     * Set a flash message.
     */
    protected function flash(string $type, string $message): void
    {
        flashSet($type, $message);
    }
}
