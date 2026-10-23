<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Renders every exception of the api/* routes as RFC 9457 (application/problem+json)
 * with a stable code in the "error" extension, like the other UBO APIs. Failures of
 * the OAuth flows (login, refresh) keep their OAuth code ("invalid_grant", ...).
 *
 * /oauth/token is not under api/*: Passport keeps answering it in the standard OAuth2
 * format ({error, error_description}) that OAuth clients and UBO.Common expect.
 *
 * Never exposes traces, internal exception messages or file paths.
 */
class ProblemRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        // A response built on purpose is sent as is.
        if (! $request->is('api/*') || $e instanceof HttpResponseException) {
            return null;
        }

        // Laravel wraps AuthorizationException in AccessDeniedHttpException before rendering.
        if ($e->getPrevious() instanceof AuthorizationException) {
            $e = $e->getPrevious();
        }

        return match (true) {
            $e instanceof OAuthTokenException => $this->oauthProblem($e),
            $e instanceof AuthenticationException => $this->problem(
                401, 'No autenticado', 'Se requiere un token de acceso válido, vigente y no revocado.', 'NO_AUTENTICADO',
            ),
            $e instanceof AuthorizationException && method_exists($e, 'scopes') => $this->problem(
                403, 'Scope insuficiente', 'El token no tiene el scope requerido: '.implode(', ', $e->scopes()).'.', 'SCOPE_INSUFICIENTE',
            ),
            $e instanceof AuthorizationException => $this->problem(
                403, 'Acceso denegado', 'No tiene permiso para realizar esta operación.', 'ACCESO_DENEGADO',
            ),
            $e instanceof ValidationException => $this->problem(
                422, 'Datos inválidos', 'Los datos enviados no son válidos.', 'VALIDACION', ['errores' => $e->errors()],
            ),
            $e instanceof ThrottleRequestsException => $this->problem(
                429, 'Límite de peticiones excedido', 'Se superó el límite de peticiones por minuto.', 'LIMITE_EXCEDIDO',
                headers: $e->getHeaders(),
            ),
            $e instanceof NotFoundHttpException => $this->problem(
                404, 'Ruta no encontrada', 'La ruta solicitada no existe.', 'RUTA_NO_ENCONTRADA',
            ),
            $e instanceof MethodNotAllowedHttpException => $this->problem(
                405, 'Método no permitido', 'El método HTTP no está permitido para esta ruta.', 'METODO_NO_PERMITIDO',
                headers: $e->getHeaders(),
            ),
            $e instanceof HttpExceptionInterface => $this->problem(
                $e->getStatusCode(), 'Error en la petición', 'La petición no pudo procesarse.', 'ERROR_HTTP',
                headers: $e->getHeaders(),
            ),
            default => $this->problem(
                500, 'Error interno', 'Ocurrió un error inesperado al procesar la petición.', 'ERROR_INTERNO',
            ),
        };
    }

    /**
     * Login and refresh failures. A misconfigured password client is the server's
     * fault: the reason goes to the log, never to the caller.
     */
    protected function oauthProblem(OAuthTokenException $e): JsonResponse
    {
        if ($e->errorType === 'invalid_client') {
            Log::error('The password grant client was rejected: '.$e->getMessage());

            return $this->problem(
                500, 'Error interno', 'El servicio de autenticación no está bien configurado.', $e->errorType,
            );
        }

        $title = match ($e->errorType) {
            'invalid_grant', 'invalid_credentials' => 'Credenciales inválidas',
            'invalid_scope' => 'Scope inválido',
            default => 'Solicitud OAuth inválida',
        };

        return $this->problem($e->status, $title, $e->getMessage(), $e->errorType);
    }

    /**
     * @param  array<string, mixed>  $extensions
     * @param  array<string, string>  $headers
     */
    protected function problem(int $status, string $title, string $detail, string $error, array $extensions = [], array $headers = []): JsonResponse
    {
        $body = [
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'error' => $error,
            ...$extensions,
        ];

        return new JsonResponse($body, $status, [...$headers, 'Content-Type' => 'application/problem+json'], JSON_UNESCAPED_UNICODE);
    }
}
