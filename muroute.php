<?php

class MuRoute
{
    private $apiPrefix, $routesPath, $cacheDirPath;
    private array $routes = [];
    private $authHandler = null;

    /**
     * Constructor for initializing the routing system.
     *
     * Accepts an optional array of options to configure the API, routes, and cache paths.
     * - `apiPrefix`: The prefix for API routes (default is '/api/').
     * - `routesPath`: The directory where route files are located (default is the 'routes' folder in the current directory).
     * - `cacheDirPath`: The directory where cached route data is stored (default is the 'cache' folder in the current directory).
     *
     * The constructor ensures that the provided paths are properly formatted with trailing slashes and loads the routes.
     *
     * @param array $options Configuration options for the API.
     */
    public function __construct($options = [])
    {
        // Set the API prefix, or use default value '/api/'
        $this->apiPrefix = $options['apiPrefix'] ?? '/api/';

        // Set the path to the routes directory, or use default value './routes/'
        $this->routesPath = $options['routesPath'] ?? __DIR__ . '/routes/';

        // Set the path to the cache directory, or use default value './cache/'
        $this->cacheDirPath = $options['cacheDirPath'] ?? __DIR__ . '/cache/';

        // Ensure the API prefix ends with a slash
        if (substr($this->apiPrefix, -1) !== '/') {
            $this->apiPrefix .= '/';
        }

        // Ensure the routes path ends with a slash
        if (substr($this->routesPath, -1) !== '/') {
            $this->routesPath .= '/';
        }

        // Ensure the cache directory path ends with a slash
        if (substr($this->cacheDirPath, -1) !== '/') {
            $this->cacheDirPath .= '/';
        }

        // Load the routes into memory
        $this->loadRoutes();
    }


    /**
     * Sets a custom authentication handler function.
     * The function accepts a single parameter, which is the authorization rule from the route definition
     * and must return `true` for authorized requests or `false` to deny access.
     *
     * @param callable $authHandler The authentication function.
     */
    public function setAuthHandler(callable $authHandler): void
    {
        $this->authHandler = $authHandler;
    }

    /**
     * Runs the registered authentication check.
     * If it returns false, a 401 Unauthorized response is sent.
     */
    private function runAuthCheck($authRule): void
    {
        if (
            !is_null($authRule) &&
            $this->authHandler &&
            !call_user_func($this->authHandler, $authRule)
        ) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
    }

    /**
     * Loads routes from cache or scans for them if cache is missing.
     */
    private function loadRoutes(): void
    {
        $cacheFile = $this->cacheDirPath . 'routes.json';

        if (file_exists($cacheFile)) {
            $this->routes = json_decode(file_get_contents($cacheFile), true);
        } else {
            $this->routes = $this->scanRoutes();
            $this->cacheRoutes($cacheFile);
        }
    }

    /**
     * Matches the current request path and method to a registered route.
     *
     * @param string $path The route path to check.
     * @param string|array|null $methods Allowed HTTP methods for the route.
     * @return bool Returns true if the route matches, false otherwise.
     */
    private function matchesRoute(string $path, string|array|null $methods): bool
    {
        $currentMethod = $_SERVER['REQUEST_METHOD'];
        $currentPath = substr($_SERVER['REQUEST_URI'], strlen($this->apiPrefix));
        $currentPath = ltrim($currentPath, '/');
        $path = ltrim($path, '/');

        // Validate request method
        if ($methods !== null) {
            if (is_string($methods) && $currentMethod !== $methods) {
                return false;
            }
            if (is_array($methods) && !in_array($currentMethod, $methods, true)) {
                return false;
            }
        }

        // Split path into segments and compare
        $pathSegments = explode('/', $path);
        $currentSegments = explode('/', $currentPath);

        if (count($pathSegments) !== count($currentSegments)) {
            return false;
        }

        // Match segments and extract parameters
        $params = [];
        foreach ($pathSegments as $index => $segment) {
            if ($segment !== $currentSegments[$index]) {
                if ($segment[0] !== ':') {
                    return false;
                }
                $params[substr($segment, 1)] = $currentSegments[$index];
            }
        }

        // Merge parameters into $_REQUEST
        $_REQUEST = array_merge($_REQUEST, $params);
        return true;
    }

    /**
     * Scans the routes directory recursively for route definitions.
     *
     * @return array The discovered routes.
     */
    private function scanRoutes(): array
    {
        $routes = [];
        $dirs = [$this->routesPath];

        while ($dir = array_shift($dirs)) {
            foreach (scandir($dir) as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $filePath = "$dir/$file";
                if (is_dir($filePath)) {
                    $dirs[] = $filePath;
                } elseif (str_ends_with($file, '.php')) {
                    $routeInfo = $this->extractRouteInfo($filePath);
                    if ($routeInfo) {
                        $routes[] = $routeInfo;
                    }
                }
            }
        }
        return $routes;
    }

    /**
     * Extracts route information from a PHP file.
     *
     * @param string $filePath The file to analyze.
     * @return array|null The route data, or null if not found.
     */
    private function extractRouteInfo(string $filePath): ?array
    {
        $content = file_get_contents($filePath, false, null, 0, 256);

        if (!str_starts_with($content, '<?php') || !str_contains($content, '@route')) {
            return null;
        }

        // Extract route definition
        $lines = array_slice(explode("\n", $content), 0, 5);
        $routeLine = null;
        foreach ($lines as $line) {
            if (str_contains(trim($line), '@route')) {
                $routeLine = trim(substr($line, strpos($line, '@route') + 6));
                break;
            }
        }

        if (!$routeLine) {
            return null;
        }

        // Extract methods if defined in brackets
        $methods = null;
        if (str_ends_with($routeLine, ']')) {
            $lastBracket = strrpos($routeLine, '[');
            if ($lastBracket !== false) {
                $methods = explode(',', trim(substr($routeLine, $lastBracket + 1, -1)));
                $methods = array_map('strtoupper', array_map('trim', array_filter($methods)));
                $routeLine = trim(substr($routeLine, 0, $lastBracket));
            } else {
                throw new Exception("Invalid route definition in $filePath");
            }
        }

        // extract authorization rules if defined (e.g. @auth)
        $auth = null;
        foreach ($lines as $line) {
            if (str_contains(trim($line), '@auth')) {
                $auth = trim(substr($line, strpos($line, '@auth') + 5));
                break;
            }
        }
        if ($auth) $auth = trim($auth);
        return [
            'route' => $routeLine,
            'methods' => $methods ?? null,
            'auth' => $auth ?? null,
            'file' => $filePath,
        ];
    }

    /**
     * Caches route definitions to a file.
     *
     * @param string $cacheFile The file path to store the cached routes.
     */
    private function cacheRoutes(string $cacheFile): void
    {
        if (!is_dir(dirname($cacheFile))) mkdir(dirname($cacheFile), 0777, true);
        file_put_contents($cacheFile, json_encode($this->routes));
    }

    /**
     * Searches for a matching route and executes it if found.
     *
     * @return bool Returns true if a route was found and executed, false otherwise.
     */
    public function handleRequest(): bool
    {
        foreach ($this->routes as $route) {
            if ($this->matchesRoute($route['route'], $route['methods'])) {
                $this->runAuthCheck($route['auth']);
                include_once $route['file'];
                return true;
            }
        }
        return false;
    }

    /**
     * Sends a 404 response if no route is found.
     */
    private function sendNotFoundResponse(): void
    {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Route not found']);
        exit;
    }

    /**
     * Runs the router to handle the current request.
     */
    public function run(): void
    {
        if (!$this->handleRequest()) {
            $this->sendNotFoundResponse();
        }
    }
}
