<?php

namespace App\Http\Middleware;

use App\Models\ActionLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AutomaticActionLogMiddleware
{
    private const TRACKED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        $shouldTrack = $this->shouldTrack($request);
        $response = $next($request);

        if ($shouldTrack) {
            $this->recordFallbackLog($request, $response);
        }

        return $response;
    }

    private function shouldTrack(Request $request): bool
    {
        return $request->is('api/*')
            && in_array($request->method(), self::TRACKED_METHODS, true);
    }

    private function recordFallbackLog(Request $request, Response $response): void
    {
        if (ActionLog::wasRecordedForCurrentRequest($request)) {
            return;
        }

        $user = $request->user();
        if (!$user) {
            return;
        }

        $statusCode = $response->getStatusCode();
        $route = $request->route();
        $controllerClass = $route?->getControllerClass();
        $controllerBase = $controllerClass ? class_basename($controllerClass) : null;
        $actionMethod = $route?->getActionMethod();
        $path = trim($request->path(), '/');

        [$modelType, $modelId, $routeParams] = $this->resolveRouteContext($request);

        if (!$modelType && $controllerBase) {
            $modelType = Str::replaceLast('Controller', '', $controllerBase);
        }

        $action = $this->buildActionName($controllerBase, $actionMethod, $path, $request->method());
        $description = $this->buildDescription($request, $controllerBase, $actionMethod, $statusCode, $path);

        ActionLog::log(
            $action,
            $description,
            $modelType,
            $modelId,
            $statusCode >= 400 ? 'failed' : 'success',
            [
                'auto_recorded' => true,
                'http_method' => $request->method(),
                'path' => $path,
                'route_uri' => $route?->uri(),
                'controller' => $controllerBase,
                'action_method' => $actionMethod,
                'response_status' => $statusCode,
                'user_role' => $user->role,
                'route_params' => $routeParams,
            ]
        );
    }

    private function buildActionName(?string $controllerBase, ?string $actionMethod, string $path, string $httpMethod): string
    {
        if ($controllerBase && $actionMethod && $actionMethod !== '__invoke') {
            $base = Str::replaceLast('Controller', '', $controllerBase) . '_' . $actionMethod;
        } else {
            $base = $httpMethod . '_' . str_replace('/', '_', $path);
        }

        return Str::limit('auto_' . Str::snake($base), 120, '');
    }

    private function buildDescription(Request $request, ?string $controllerBase, ?string $actionMethod, int $statusCode, string $path): string
    {
        $description = ($statusCode >= 400 ? 'Attempted' : 'Performed') . ' ' . $request->method() . ' ' . $path;

        if ($controllerBase && $actionMethod && $actionMethod !== '__invoke') {
            $description .= " via {$controllerBase}@{$actionMethod}";
        }

        if ($statusCode >= 400) {
            $description .= " (HTTP {$statusCode})";
        }

        return $description;
    }

    private function resolveRouteContext(Request $request): array
    {
        $parameters = [];
        $modelType = null;
        $modelId = null;

        foreach ($request->route()?->parameters() ?? [] as $key => $value) {
            if ($value instanceof Model) {
                $parameters[$key] = $value->getKey();
                $modelType ??= class_basename($value);
                $modelId ??= $value->getKey();
                continue;
            }

            if (is_scalar($value)) {
                $parameters[$key] = $value;
                if ($modelId === null && is_numeric((string) $value)) {
                    $modelId = (int) $value;
                }
            }
        }

        return [$modelType, $modelId, $parameters];
    }
}