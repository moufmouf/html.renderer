<?php
/*
 * Copyright (c) 2013 David Negrier
 *
 * See the file LICENSE.txt for copying permission.
 */

namespace Mouf\Html\Renderer;

use Mouf\Utils\Cache\CacheInterface;
use Mouf\MoufManager;

/**
 * This class is a renderer that renders objects using other renderers.
 * This renderer will automatically detect the renderers to be included.
 * They must extend the ChainableRendererInterface interface.
 *
 * @author David Négrier <david@mouf-php.com>
 */
class AutoChainRenderer implements CanSetTemplateRendererInterface
{

    /**
     * @var ChainableRendererInterface
     */
    private $templateRenderer;
    /**
     * @var ChainableRendererInterface[]
     */
    private $packageRenderers = array();
    /**
     * @var ChainableRendererInterface[]
     */
    private $customRenderers = array();

    private $cacheService;

    private $initDone = false;
    /**
     * @var string
     */
    private $uniqueName;

    /**
     *
     * @param CacheInterface $cacheService This service is used to speed up the mapping between the object and the template.
     */
    public function __construct(CacheInterface $cacheService, string $uniqueName = 'autochain-renderer')
    {
        $this->cacheService = $cacheService;
        $this->uniqueName = $uniqueName;
    }

    /**
     * (non-PHPdoc)
     * @see \Mouf\Html\Renderer\RendererInterface::render()
     */
    public function render($object, $context = null)
    {
        $renderer = $this->getRenderer($object, $context);
        if ($renderer == null) {
            throw new \Exception("Renderer not found. Unable to find renderer for object of class '".get_class($object)."'. Path tested: ".$this->getRendererDebugMessage($object, $context));
        }
        $renderer->render($object, $context);
    }

    /**
     * Sets the renderer associated to the template.
     * There should be only one if these renderers.
     * It is the role of the template to subscribe to this renderer.
     *
     * @param RendererInterface $templateRenderer
     */
    public function setTemplateRenderer(RendererInterface $templateRenderer)
    {
        $this->templateRenderer = $templateRenderer;
    }

    private function getRenderer($object, $context = null)
    {
        $cacheKey = "chainRendererByClass_".$this->uniqueName."/".$this->templateRenderer->getUniqueName()."/".$this->getTemplateRendererInstanceName()."/".get_class($object)."/".$context;

        $rendererSource = $this->cacheService->get($cacheKey);
        if ($rendererSource != null) {
            $source = $rendererSource['source'];
            $rendererIndex = $rendererSource['index'];
            switch ($source) {
                case 'customRenderers':
                    return $this->customRenderers[$rendererIndex];
                case 'templateRenderer':
                    return $this->templateRenderer;
                case 'packageRenderers':
                    return $this->packageRenderers[$rendererIndex];
                default:
                    throw new \RuntimeException('Unexpected renderer source: "'.$source.'"');
            }
        }

        $this->initRenderersList();

        $isCachable = true;
        $foundRenderer = null;
        $source = null;
        $rendererIndex = null;

        do {
            foreach ($this->customRenderers as $key => $renderer) {
                /* @var $renderer ChainableRendererInterface */
                $result = $renderer->canRender($object, $context);
                if ($result == ChainableRendererInterface::CAN_RENDER_OBJECT || $result == ChainableRendererInterface::CANNOT_RENDER_OBJECT) {
                    $isCachable = false;
                }
                if ($result == ChainableRendererInterface::CAN_RENDER_OBJECT || $result == ChainableRendererInterface::CAN_RENDER_CLASS) {
                    $foundRenderer = $renderer;
                    $source = 'customRenderers';
                    $rendererIndex = $key;
                    break 2;
                }
            }

            /* @var $renderer ChainableRendererInterface */
            if ($this->templateRenderer) {
                $result = $this->templateRenderer->canRender($object, $context);
                if ($result == ChainableRendererInterface::CAN_RENDER_OBJECT || $result == ChainableRendererInterface::CANNOT_RENDER_OBJECT) {
                    $isCachable = false;
                }
                if ($result == ChainableRendererInterface::CAN_RENDER_OBJECT || $result == ChainableRendererInterface::CAN_RENDER_CLASS) {
                    $foundRenderer = $this->templateRenderer;
                    $source = 'templateRenderer';
                    break;
                }
            }

            foreach ($this->packageRenderers as $key => $renderer) {
                /* @var $renderer ChainableRendererInterface */
                $result = $renderer->canRender($object, $context);
                if ($result == ChainableRendererInterface::CAN_RENDER_OBJECT || $result == ChainableRendererInterface::CANNOT_RENDER_OBJECT) {
                    $isCachable = false;
                }
                if ($result == ChainableRendererInterface::CAN_RENDER_OBJECT || $result == ChainableRendererInterface::CAN_RENDER_CLASS) {
                    $foundRenderer = $renderer;
                    $source = 'packageRenderers';
                    $rendererIndex = $key;
                    break 2;
                }
            }
        } while (false);

        if ($isCachable && $foundRenderer) {
            $this->cacheService->set($cacheKey, [
                'source' => $source,
                'index' => $rendererIndex
            ]);
        }

        return $foundRenderer;
    }

    /**
     * Returns a string explaining the steps done to find the renderer.
     *
     * @param  object $object
     * @param  string $context
     * @return string
     */
    private function getRendererDebugMessage($object, $context = null)
    {
        $debugMessage = '';

        $this->initRenderersList();

        $isCachable = true;
        $foundRenderer = null;

        do {
            foreach ($this->customRenderers as $renderer) {
                /* @var $renderer ChainableRendererInterface */

                $debugMessage .= $renderer->debugCanRender($object, $context);
                $result = $renderer->canRender($object, $context);
                if ($result == ChainableRendererInterface::CAN_RENDER_OBJECT || $result == ChainableRendererInterface::CANNOT_RENDER_OBJECT) {
                    $isCachable = false;
                }
                if ($result == ChainableRendererInterface::CAN_RENDER_OBJECT || $result == ChainableRendererInterface::CAN_RENDER_CLASS) {
                    $foundRenderer = $renderer;
                    break 2;
                }
            }

            /* @var $renderer ChainableRendererInterface */
            if ($this->templateRenderer) {
                $debugMessage .= $this->templateRenderer->debugCanRender($object, $context);
                $result = $this->templateRenderer->canRender($object, $context);
                if ($result == ChainableRendererInterface::CAN_RENDER_OBJECT || $result == ChainableRendererInterface::CANNOT_RENDER_OBJECT) {
                    $isCachable = false;
                }
                if ($result == ChainableRendererInterface::CAN_RENDER_OBJECT || $result == ChainableRendererInterface::CAN_RENDER_CLASS) {
                    $foundRenderer = $this->templateRenderer;
                    break;
                }
            }

            foreach ($this->packageRenderers as $renderer) {
                /* @var $renderer ChainableRendererInterface */

                $debugMessage .= $renderer->debugCanRender($object, $context);
                $result = $renderer->canRender($object, $context);
                if ($result == ChainableRendererInterface::CAN_RENDER_OBJECT || $result == ChainableRendererInterface::CANNOT_RENDER_OBJECT) {
                    $isCachable = false;
                }
                if ($result == ChainableRendererInterface::CAN_RENDER_OBJECT || $result == ChainableRendererInterface::CAN_RENDER_CLASS) {
                    $foundRenderer = $renderer;
                    break 2;
                }
            }

        } while (false);
		
		return $debugMessage;
	}
	
	/**
	 * Initializes the renderers list (from cache if available)
	 */
	private function initRenderersList() {
        if (!$this->initDone) {
            $moufManager = MoufManager::getMoufManager();
            // TODO: suboptimal. findInstanceName is not efficient.
            $instanceName = $this->getInstanceName();
            $renderersList = $this->cacheService->get("chainRenderer_" . $instanceName);
            if ($renderersList === null) {
                $renderersList = $this->queryRenderersList();
                $this->cacheService->set("chainRenderer_" . $instanceName, $renderersList);
            }

            if (isset($renderersList[ChainableRendererInterface::TYPE_CUSTOM])) {
                $this->customRenderers = array_map(function ($name) use ($moufManager) {
                    return $moufManager->getInstance($name);
                }, $renderersList[ChainableRendererInterface::TYPE_CUSTOM]);
            }

            if (isset($renderersList[ChainableRendererInterface::TYPE_PACKAGE])) {
                $this->packageRenderers = array_map(function ($name) use ($moufManager) {
                    return $moufManager->getInstance($name);
                }, $renderersList[ChainableRendererInterface::TYPE_PACKAGE]);
            }

            // Note: We ignore template renderers on purpose.

            $this->initDone = true;
        }
	}
	
	/**
	 * Returns an orderered list of renderers instance name to apply, separated by "type".
	 * 
	 * @return array<string, string[]>
	 */
	private function queryRenderersList() {
		$moufManager = MoufManager::getMoufManager();
		$renderersNames = $moufManager->findInstances('Mouf\\Html\\Renderer\\ChainableRendererInterface');
		
		foreach ($renderersNames as $name) {
			$renderers[$name] = $moufManager->getInstance($name);
		}
		
		$renderersByType = array();
		foreach ($renderers as $name => $renderer) {
			/* @var $renderer ChainableRendererInterface */
			$renderersByType[$renderer->getRendererType()][] = $name;
		}
		
		// Now, let's sort the renderers by priority (highest first).
		$renderersByType = array_map(function(array $innerArray) use ($renderers) {
			usort($innerArray, function($name1, $name2) use ($renderers) {
				$item1 = $renderers[$name1];
				$item2 = $renderers[$name2];
				return $item2->getPriority() - $item1->getPriority();
			});
			return $innerArray;
		}, $renderersByType);
		
		return $renderersByType;
	}
}
