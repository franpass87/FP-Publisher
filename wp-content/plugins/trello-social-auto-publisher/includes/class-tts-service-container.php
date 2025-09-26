<?php
namespace {
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }
}

namespace Psr\Container {
    if ( ! interface_exists( __NAMESPACE__ . '\\ContainerInterface' ) ) {
        interface ContainerInterface {
            public function get( string $id );
            public function has( string $id ): bool;
        }
    }

    if ( ! interface_exists( __NAMESPACE__ . '\\ContainerExceptionInterface' ) ) {
        interface ContainerExceptionInterface extends \Throwable {}
    }

    if ( ! interface_exists( __NAMESPACE__ . '\\NotFoundExceptionInterface' ) ) {
        interface NotFoundExceptionInterface extends ContainerExceptionInterface {}
    }
}

namespace {
    use Psr\Container\ContainerExceptionInterface;
    use Psr\Container\ContainerInterface;
    use Psr\Container\NotFoundExceptionInterface;

    /**
     * Generic container exception.
     */
    class TTS_Service_Exception extends \Exception implements ContainerExceptionInterface {}

    /**
     * Exception thrown when a service cannot be located.
     */
    class TTS_Service_Not_Found_Exception extends TTS_Service_Exception implements NotFoundExceptionInterface {}

    /**
     * Lightweight PSR-11 compatible service container.
     */
    class TTS_Service_Container implements ContainerInterface {

        /**
         * Registered service definitions.
         *
         * @var array<string, callable>
         */
        private $definitions = array();

        /**
         * Indicates which services are shared singletons.
         *
         * @var array<string, bool>
         */
        private $shared = array();

        /**
         * Cached shared service instances.
         *
         * @var array<string, mixed>
         */
        private $resolved = array();

        /**
         * Tracks services currently being resolved to detect circular dependencies.
         *
         * @var array<string, bool>
         */
        private $resolving = array();

        /**
         * Register a service definition with the container.
         *
         * @param string               $id        Service identifier.
         * @param callable|string|mixed $concrete Service factory or value.
         * @param bool                 $shared    Whether the service is shared.
         *
         * @return void
         *
         * @throws TTS_Service_Exception When the identifier is invalid.
         */
        public function set( $id, $concrete, $shared = true ) {
            if ( ! is_string( $id ) || '' === trim( $id ) ) {
                throw new TTS_Service_Exception( 'Service identifier must be a non-empty string.' );
            }

            $id = trim( $id );

            if ( is_object( $concrete ) && ! is_callable( $concrete ) ) {
                $this->resolved[ $id ] = $concrete;
                $this->shared[ $id ]   = true;
                unset( $this->definitions[ $id ] );
                return;
            }

            if ( is_string( $concrete ) && class_exists( $concrete ) ) {
                $class    = $concrete;
                $concrete = function () use ( $class ) {
                    return new $class();
                };
            } elseif ( ! is_callable( $concrete ) ) {
                $value    = $concrete;
                $concrete = function () use ( $value ) {
                    return $value;
                };
            }

            $this->definitions[ $id ] = $concrete;
            $this->shared[ $id ]      = (bool) $shared;
            unset( $this->resolved[ $id ] );
        }

        /**
         * Determine whether the container has a service definition or resolved instance.
         *
         * @param string $id Service identifier.
         *
         * @return bool
         */
        public function has( string $id ): bool {
            $id = (string) $id;
            return array_key_exists( $id, $this->definitions ) || array_key_exists( $id, $this->resolved );
        }

        /**
         * Retrieve a service from the container.
         *
         * @param string $id Service identifier.
         *
         * @return mixed
         *
         * @throws TTS_Service_Not_Found_Exception When the service is not registered.
         * @throws TTS_Service_Exception           When the service cannot be created.
         */
        public function get( string $id ) {
            $id = (string) $id;

            if ( array_key_exists( $id, $this->resolved ) ) {
                return $this->resolved[ $id ];
            }

            if ( ! array_key_exists( $id, $this->definitions ) ) {
                throw new TTS_Service_Not_Found_Exception( sprintf( 'Service "%s" is not registered.', $id ) );
            }

            if ( isset( $this->resolving[ $id ] ) ) {
                throw new TTS_Service_Exception( sprintf( 'Circular dependency detected while resolving "%s".', $id ) );
            }

            $this->resolving[ $id ] = true;

            try {
                $definition = $this->definitions[ $id ];
                $service    = $this->invoke_definition( $definition );
            } catch ( TTS_Service_Exception $exception ) {
                unset( $this->resolving[ $id ] );
                throw $exception;
            } catch ( \Throwable $exception ) {
                unset( $this->resolving[ $id ] );
                throw new TTS_Service_Exception(
                    sprintf( 'Error while resolving service "%s": %s', $id, $exception->getMessage() ),
                    0,
                    $exception
                );
            }

            unset( $this->resolving[ $id ] );

            if ( ! empty( $this->shared[ $id ] ) ) {
                $this->resolved[ $id ] = $service;
            }

            return $service;
        }

        /**
         * Convenience helper to register a non-shared factory.
         *
         * @param string   $id       Service identifier.
         * @param callable $factory  Service factory.
         *
         * @return void
         */
        public function factory( $id, callable $factory ) {
            $this->set( $id, $factory, false );
        }

        /**
         * Execute a registered definition, injecting the container when supported.
         *
         * @param callable $definition Service definition.
         *
         * @return mixed
         */
        private function invoke_definition( $definition ) {
            if ( ! is_callable( $definition ) ) {
                return $definition;
            }

            $callable   = $definition;
            $reflection = $this->reflect_callable( $callable );
            $arguments  = array();

            if ( $reflection && $reflection->getNumberOfParameters() > 0 ) {
                $arguments[] = $this;
            }

            return call_user_func_array( $callable, $arguments );
        }

        /**
         * Build a reflection instance for a callable.
         *
         * @param callable $callable Callable service definition.
         *
         * @return ReflectionFunction|ReflectionMethod|null
         */
        private function reflect_callable( $callable ) {
            if ( $callable instanceof \Closure ) {
                return new \ReflectionFunction( $callable );
            }

            if ( is_array( $callable ) ) {
                if ( count( $callable ) !== 2 ) {
                    return null;
                }

                return new \ReflectionMethod( $callable[0], $callable[1] );
            }

            if ( is_object( $callable ) && method_exists( $callable, '__invoke' ) ) {
                return new \ReflectionMethod( $callable, '__invoke' );
            }

            if ( is_string( $callable ) && function_exists( $callable ) ) {
                return new \ReflectionFunction( $callable );
            }

            return null;
        }
    }

    /**
     * Lightweight logger adapter compatible with dependency injection.
     */
    class TTS_Logger_Service {

        /**
         * Proxy log requests to the static logger implementation.
         *
         * @param mixed  $message Log message.
         * @param string $level   Log level.
         * @param array  $context Log context.
         *
         * @return void
         */
        public function log( $message, $level = 'info', $context = array() ) {
            if ( class_exists( 'TTS_Logger' ) ) {
                \TTS_Logger::log( $message, $level, $context );
            }
        }
    }
}
