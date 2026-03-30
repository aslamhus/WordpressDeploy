<?php

namespace Yashus\WPD\Main\Push\Hooks;

interface HookInterface
{

    public function run(): self;

    public function cleanup(): void;
}
