<?php declare(strict_types=1);

namespace Slim\AnnotationRouter;

use Doctrine\Common\Annotations\Annotation\IgnoreAnnotation;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\DocParser;
use Slim\AnnotationRouter\Annotations\Middleware;
use Slim\AnnotationRouter\Annotations\Route;
use Slim\AnnotationRouter\Annotations\RoutePrefix;
use Slim\AnnotationRouter\Loader\AnnotationClassLoader;
use Slim\AnnotationRouter\Loader\AnnotationDirectoryLoader;
use Slim\Interfaces\RouteInterface;
use Slim\Routing\RouteCollector;

/**
 * Class AnnotationRouteCollector
 *
 * @since 21.04.2019
 * @author Daniel Tęcza
 * @package Slim\AnnotationRouter
 */
class AnnotationRouteCollector extends RouteCollector
{
    /** @var string[] */
    protected $annotationImports = [
        'ignoreAnnotation' => IgnoreAnnotation::class,
        'route' => Route::class,
        'routeprefix' => RoutePrefix::class,
        'middleware' => Middleware::class,
    ];

    /** @var string|null */
    protected $defaultControllersPath;

    /**
     * @param string $path
     *
     * @return \Slim\AnnotationRouter\AnnotationRouteCollector
     */
    public function setDefaultControllersPath(string $path): AnnotationRouteCollector
    {
        $this->defaultControllersPath = $path;

        return $this;
    }

    /**
     * @return string
     */
    public function getDefaultControllersPath(): ?string
    {
        return $this->defaultControllersPath;
    }

    /**
     * @return bool
     */
    public function collectRoutes(): bool
    {
        $directoryPath = $this->getDefaultControllersPath();

        if ($directoryPath === null || !is_dir($directoryPath)) {
            throw new \RuntimeException('Directory path for controllers must be defined!', 500);
        }

        $docParser = new DocParser();
        $docParser->setIgnoreNotImportedAnnotations( true );

        foreach ($this->annotationImports as $class) {
            AnnotationRegistry::loadAnnotationClass($class);
        }

        try {
            $annotationReader = new AnnotationReader($docParser);

            $reflection = new \ReflectionProperty(AnnotationReader::class, 'globalImports');
            $reflection->setAccessible(true);
            $reflection->setValue(null, $this->annotationImports);

            $annotationDirectoryLoader = new AnnotationDirectoryLoader(new AnnotationClassLoader($annotationReader, $this));
            $routes = $annotationDirectoryLoader->load($directoryPath);
        } catch (\Throwable $ex) {
            $routes = [];
        }

        /** @var RouteInterface $route */
        foreach ($routes as $route) {
            $this->routes[$route->getIdentifier()] = $route;
            $this->routeCounter++;
        }

        return $this->routeCounter > 0;
    }

    /**
     * @param array $methods
     * @param string $pattern
     * @param $callable
     *
     * @return \Slim\Interfaces\RouteInterface
     */
    public function createRoute(array $methods, string $pattern, $callable): RouteInterface
    {
        return parent::createRoute($methods, $pattern, $callable);
    }
}
