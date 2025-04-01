# MuRoute - A Minimalistic Routing System for PHP

MuRoute is a **tiny, single-file** PHP routing system designed for **raw PHP projects**. It provides a lightweight **API-like** experience without the complexity of a full-fledged framework.

With MuRoute, you can keep writing your **PHP scripts the "shitty" way**, no fancy controllers, no dependency injection, just **pure PHP files** inside a routes directory. MuRoute scans them, extracts their route definitions, caches them, and maps requests accordingly.

## Features
- **Super lightweight** – Single-file routing system, no dependencies.
- **Automatic Route Discovery** – Define routes inside PHP files with `//@route` directives.
- **Dynamic Parameters** – Extract variables from the URL easily.
- **Centralized Authentication** – Define a global authentication handler with `setAuthHandler()`.
- **Route Caching** – Routes are cached for faster execution (cache must be cleared when modifying routes).

---

## Installation

Just **download MuRoute.php** and include it in your project:

```
require_once 'MuRoute.php';

$router = new MuRoute([
    'apiPrefix' => '/api/',      // Default is "/api/"
    'routesPath' => __DIR__ . '/routes/',  // Default is "./routes/"
    'cacheDirPath' => __DIR__ . '/cache/'  // Default is "./cache/"
]);

$router->run();
```

That's it! Your routes will now be automatically detected.

---

## Defining Routes

MuRoute discovers routes by scanning PHP files inside the **routes directory**. To define a route, simply add a `//@route` directive at the beginning of the file:

### Basic Route
```
//@route /hello
<?php
echo "Hello, world!";
```

### Route with URL Parameters
MuRoute supports **dynamic parameters** using `:param`:

```
//@route /user/:id
<?php
echo "User ID: " . $_REQUEST['id'];
```

### Route with Allowed HTTP Methods
Different HTTP methods can be mapped to different files, allowing clear separation of logic:

```
// fetchUser.php
//@route /user/:id [GET]
<?php
echo "Fetching user " . $_REQUEST['id'];
```

```
// updateUser.php
//@route /user/:id [POST]
<?php
echo "Updating user " . $_REQUEST['id'];
```

MuRoute will automatically route **GET** requests to `fetchUser.php` and **POST** requests to `updateUser.php`.

---

## Authentication

You can define an **authentication rule** inside your route file using `//@auth`. The rule string is passed to the custom authentication handler.

### Example: Protecting a Route
```
//@route /admin
//@auth admin_only
<?php
echo "Welcome, Admin!";
```

### Setting Up an Authentication Handler
Define a **global authentication handler** in your MuRoute instance:

```
$router->setAuthHandler(function($rule) {
    if ($rule === 'admin_only' && !isset($_SESSION['admin'])) {
        return false;
    }
    return true;
});
```

If authentication fails, the request will return a **401 Unauthorized** response.

---

## Route Caching

To improve performance, MuRoute **caches the route definitions** inside a JSON file. This means:
- Routes load **instantly** after the first request.
- If you modify route files, **clear the cache directory manually** to apply changes.

Cache location can be configured in the constructor:

```
$router = new MuRoute([
    'cacheDirPath' => __DIR__ . '/my_cache/'
]);
```

---

## Running MuRoute

Once everything is set up, just call `run()`:

```
$router->run();
```

If no route matches, a **404 Not Found** response is sent automatically.

---

## License

MuRoute is released under the **WTFPL**. Do whatever you want with it.
