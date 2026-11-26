<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use ReflectionMethod;

/**
 * Keep a resource's own canView() / canEdit() / canCreate() as the rule for its pages.
 *
 * Filament 4.12+ authorises pages through the model POLICY first and lets a resource's
 * can*() override only narrow that, never widen it. Several Haraan resources grant
 * access in can*() that the Shield policies underneath don't (a partner editing their
 * own event or venue, OPS viewing app users), so after the upgrade those people were
 * shut out with 403.
 *
 * These resources' can*() methods carry the real rules (role, ownership, panel), so we
 * route page authorisation back to them, but only for the methods the resource itself
 * overrides. A method it doesn't override keeps Filament's policy-based behaviour, which
 * also avoids canView() → getViewAuthorizationResponse() → canView() recursion.
 */
trait ResourceRulesDecidePageAccess
{
    public static function getCreateAuthorizationResponse(): Response
    {
        if (! self::overridesOwnRule('canCreate')) {
            return parent::getCreateAuthorizationResponse();
        }

        return static::canCreate() ? Response::allow() : Response::deny();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        if (! self::overridesOwnRule('canEdit')) {
            return parent::getEditAuthorizationResponse($record);
        }

        return static::canEdit($record) ? Response::allow() : Response::deny();
    }

    public static function getViewAuthorizationResponse(Model $record): Response
    {
        if (! self::overridesOwnRule('canView')) {
            return parent::getViewAuthorizationResponse($record);
        }

        return static::canView($record) ? Response::allow() : Response::deny();
    }

    private static function overridesOwnRule(string $method): bool
    {
        return (new ReflectionMethod(static::class, $method))->getDeclaringClass()->getName() !== \Filament\Resources\Resource::class;
    }
}
