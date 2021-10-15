<?php
namespace Neos\Fusion\Aspects;

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Fusion\Annotations\LexerMode;
use Neos\Flow\Reflection\ReflectionService;

/**
 * @Flow\Scope("singleton")
 * @Flow\Aspect
 */
class FusionParserAspect
{
    /**
     * @Flow\Inject
     * @var ReflectionService
     */
    protected $reflectionService;

    /**
     * @Flow\Around("methodAnnotatedWith(Neos\Fusion\Annotations\LexerMode)")
     * @param JoinPointInterface $joinPoint The current join point
     */
    public function lexerSwitchMode(JoinPointInterface $joinPoint)
    {
        $annotation = $this->reflectionService->getMethodAnnotation(
            $joinPoint->getClassName(),
            $joinPoint->getMethodName(),
            LexerMode::class
        );

        $joinPoint->getProxy()->pushState($annotation->lexerStateId);

        $result = $joinPoint->getAdviceChain()->proceed($joinPoint);

        $joinPoint->getProxy()->popState();

        return $result;
    }
}
