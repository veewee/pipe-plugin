<?php

declare(strict_types=1);

namespace Psl\Psalm;

use Psalm\Plugin\PluginEntryPointInterface;
use Psalm\Plugin\RegistrationInterface;
use SimpleXMLElement;

final class Plugin implements PluginEntryPointInterface
{
    public function __invoke(RegistrationInterface $registration, ?SimpleXMLElement $config = null): void
    {
        require_once __DIR__.'/PipeArgumentsProvider.php';

        $registration->registerHooksFromClass(PipeArgumentsProvider::class);
    }
}
