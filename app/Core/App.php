<?php

declare(strict_types=1);

namespace FixPoint\Core;

use FixPoint\Controllers\HomeController;
use FixPoint\Controllers\ServicesController;
use FixPoint\Middleware\SecurityHeaders;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;
use Throwable;

/**
 * Application kernel: service container, route dispatch, error policy.
 * One instance exists per process; helpers and middleware obtain it via app().
 */
final class App
{
    private static ?self $instance = null;

    /** @var array<class-string, object> */
    private array $instances = [];

    private ?Router $router = null;

    private Logger $logger;

    private SecurityHeaders $securityHeaders;

    public function __construct(private readonly Config $config)
    {
        self::$instance = $this;
        $this->logger = new Logger((string) $config->get('logging.path'));
        $this->securityHeaders = new SecurityHeaders($config);
        $this->instances[Config::class] = $config;
        $this->instances[Logger::class] = $this->logger;
        $this->instances[SecurityHeaders::class] = $this->securityHeaders;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new RuntimeException('Application has not been bootstrapped.');
        }

        return self::$instance;
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function logger(): Logger
    {
        return $this->logger;
    }

    public function database(): Database
    {
        return $this->make(Database::class);
    }

    public function session(): Session
    {
        return $this->make(Session::class);
    }

    public function view(): ViewRenderer
    {
        return $this->make(ViewRenderer::class);
    }

    public function csrf(): \FixPoint\Security\Csrf
    {
        return $this->make(\FixPoint\Security\Csrf::class);
    }

    public function auth(): \FixPoint\Security\Auth
    {
        return $this->make(\FixPoint\Security\Auth::class);
    }

    /**
     * Small reflection container. Only class-typed constructor dependencies are
     * resolved; scalar parameters must have defaults.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public function make(string $class): object
    {
        if (isset($this->instances[$class])) {
            return $this->instances[$class];
        }

        $reflection = new ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw new RuntimeException('Class is not instantiable: ' . $class);
        }

        $constructor = $reflection->getConstructor();
        $arguments = [];

        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                /** @var class-string $dependency */
                $dependency = $type->getName();
                $arguments[] = $this->make($dependency);
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            throw new RuntimeException(sprintf(
                'Cannot resolve parameter $%s of %s',
                $parameter->getName(),
                $class
            ));
        }

        $object = $reflection->newInstanceArgs($arguments);
        $this->instances[$class] = $object;

        return $object;
    }

    public function router(): Router
    {
        if ($this->router instanceof Router) {
            return $this->router;
        }

        $router = new Router();
        $register = require $this->config->get('paths.base') . '/config/routes.php';
        if (!is_callable($register)) {
            throw new RuntimeException('config/routes.php must return a callable.');
        }
        $register($router, $this);

        return $this->router = $router;
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->dispatch($request);
        } catch (HttpException $exception) {
            $response = $this->httpError($request, $exception);
        } catch (Throwable $exception) {
            $response = $this->serverError($request, $exception);
        }

        return $this->securityHeaders->apply($response, $request);
    }

    public function runHttp(): void
    {
        // Never expose PHP warnings, SQL or stack traces in HTTP output.
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        try {
            $this->session()->start();
            $request = Request::fromGlobals();
            $response = $this->handle($request);
        } catch (Throwable $exception) {
            $request = Request::fromGlobals();
            $response = $this->securityHeaders->apply($this->serverError($request, $exception), $request);
        }

        $response->send($request->method() !== 'HEAD');
    }

    private function dispatch(Request $request): Response
    {
        $match = $this->router()->match($request);

        $core = function (Request $current) use ($match): Response {
            $handler = $match->handler;
            if (is_array($handler)) {
                $controller = $this->make($handler[0]);
                $response = $controller->{$handler[1]}($current, $match->params);
            } else {
                $response = $handler($current, $match->params);
            }

            if (!$response instanceof Response) {
                throw new RuntimeException('Route handler must return a Response.');
            }

            return $response;
        };

        $pipeline = $core;
        foreach (array_reverse($match->middleware) as $middleware) {
            $instance = is_string($middleware) ? $this->make($middleware) : $middleware;
            $next = $pipeline;
            $pipeline = static fn (Request $current): Response => $instance->handle($current, $next);
        }

        return $pipeline($request);
    }

    private function httpError(Request $request, HttpException $exception): Response
    {
        if ($exception->statusCode >= 500) {
            return $this->serverError($request, $exception);
        }

        [$title, $message, $code] = $this->errorMeta($exception->statusCode);

        if ($request->expectsJson()) {
            return Response::json(['error' => $code], $exception->statusCode, $exception->headers);
        }

        return $this->renderErrorPage($exception->statusCode, $title, $message, null, $exception->headers);
    }

    private function serverError(Request $request, Throwable $exception): Response
    {
        $errorId = bin2hex(random_bytes(8));

        $this->logger->error('Unhandled application error.', [
            'error_id' => $errorId,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'method' => $request->method(),
            'path' => $request->path(),
        ]);

        if ($request->expectsJson()) {
            return Response::json(['error' => 'server_error', 'error_id' => $errorId], 500);
        }

        return $this->renderErrorPage(
            500,
            'Внутренняя ошибка',
            'Не удалось выполнить запрос. Попробуйте позже.',
            $errorId
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function renderErrorPage(
        int $status,
        string $title,
        string $message,
        ?string $errorId,
        array $headers = []
    ): Response {
        try {
            return $this->view()->renderError($status, [
                'title' => $title,
                'message' => $message,
                'errorId' => $errorId,
                'pageTitle' => $title . ' — ' . $this->config->get('app.name'),
            ])->withHeaders($headers);
        } catch (Throwable $exception) {
            $this->logger->error('Error view rendering failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return Response::html(
                '<!doctype html><html lang="ru"><meta charset="utf-8"><title>'
                . $status . '</title><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1></html>',
                $status,
                $headers
            );
        }
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function errorMeta(int $status): array
    {
        return match ($status) {
            403 => ['Доступ запрещён', 'У вашей учётной записи нет прав на это действие.', 'forbidden'],
            404 => ['Страница не найдена', 'Запрошенная страница или запись не существует.', 'not_found'],
            405 => ['Метод не поддерживается', 'Для этого адреса используется другой HTTP-метод.', 'method_not_allowed'],
            419 => ['Сессия истекла', 'Обновите страницу и повторите действие.', 'csrf_token_invalid'],
            default => ['Ошибка запроса', 'Запрос не может быть выполнен.', 'request_error'],
        };
    }
}
