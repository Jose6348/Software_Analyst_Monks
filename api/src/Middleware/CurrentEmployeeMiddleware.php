<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\ApiException;
use App\Model\Employee;
use App\Repository\EmployeeRepository;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Simula a autenticação: resolve o líder atual a partir do header `X-Employee-Id`.
 * É o único ponto que precisaria mudar para um auth real — nada abaixo dele sabe de onde
 * veio a identidade.
 */
final readonly class CurrentEmployeeMiddleware implements MiddlewareInterface
{
    public const ATTRIBUTE = 'currentEmployee';

    private const HEADER = 'X-Employee-Id';

    public function __construct(private EmployeeRepository $employees)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine(self::HEADER);

        if ($header === '') {
            throw ApiException::badRequest(
                'Header X-Employee-Id é obrigatório.',
                'MISSING_EMPLOYEE_ID',
            );
        }

        if (preg_match('/^[1-9][0-9]*$/', $header) !== 1) {
            throw ApiException::badRequest(
                'Header X-Employee-Id deve ser um inteiro positivo.',
                'INVALID_EMPLOYEE_ID',
            );
        }

        $employee = $this->employees->findById((int) $header)
            ?? throw ApiException::notFound('Funcionário não encontrado.');

        return $handler->handle($request->withAttribute(self::ATTRIBUTE, $employee));
    }

    public static function from(ServerRequestInterface $request): Employee
    {
        $employee = $request->getAttribute(self::ATTRIBUTE);

        if (!$employee instanceof Employee) {
            throw new LogicException('Rota sem CurrentEmployeeMiddleware.');
        }

        return $employee;
    }
}
