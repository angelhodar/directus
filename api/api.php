<?php

// Composer autoloader
require 'vendor/autoload.php';

require dirname(__FILE__) . '/config.php';
require dirname(__FILE__) . '/core/db.php';
require dirname(__FILE__) . '/core/media.php';
require dirname(__FILE__) . '/core/functions.php';

define('API_VERSION', 1);
// Shortcut for router:
$V = API_VERSION;

/**
 * Slim Bootstrap
 */

$app = new \Slim\Slim(array(
    'mode'    => 'development'
));

$collection =  isset($_GET['api_collection']) ? $_GET['api_collection'] : null;
$db = new DB(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
$params = $_GET;
$data = json_decode(file_get_contents('php://input'), true);

/**
 * Slim Routes
 * (Collections arranged alphabetically)
 */

/** ACTIVITY COLLECTION
 */

// RewriteRule ^1/activity/*$                api.php?api_collection=activity&%{QUERY_STRING} [L]

$app->get("/$V/activity/", function () use ($db, $params, $data, $app) {
    $response = $db->get_activity();
    JsonView::render($response);
});


/** COLUMNS COLLECTION
 */

// RewriteRule ^1/tables/([^/]+)/columns/*$        api.php?api_collection=columns&api_response_type=json&table_name=$1&%{QUERY_STRING} [L]
// RewriteRule ^1/tables/([^/]+)/columns/([^/]+)/*$    api.php?api_collection=columns&api_response_type=json&table_name=$1&column_name=$2&%{QUERY_STRING} [L]

// GET all table columns, or POST one new table column
$app->map("/$V/tables/:table/columns", function ($table) use ($db, $params, $data, $app) {
    if($app->request()->isPost()) {
        /* @TODO improves readability: use two separate methods for fetching one vs all entries */
        $params['column_name'] = $db->add_column($table, $data); // NOTE Alters the behavior of db#get_table below
    }
    $response = $db->get_table($table, $params);
    JsonView::render($response);
})->via('GET', 'POST');

// GET or PUT one column
$app->map("/$V/tables/:table/columns/:column", function ($table, $column) use ($db, $params, $data, $app) {
    $params['column_name'] = $column;
    // Add table name to dataset. @TODO more clarification would be useful
    foreach ($data as &$row) {
        $row['table_name'] = $table;
    }
    if($app->request()->isPut()) {
        $db->set_entries('directus_columns', $data);
    }
    $response = $db->get_table($table, $params);
    JsonView::render($response);
})->via('GET', 'PUT');


/** ENTRIES COLLECTION
 */

// RewriteRule ^1/tables/([^/]+)/rows$           api.php?api_collection=entries&api_response_type=json&table_name=$1&%{QUERY_STRING} [L]
// RewriteRule ^1/tables/([^/]+)/rows/([^/]+)/*$       api.php?api_collection=entries&api_response_type=json&table_name=$1&id=$2&%{QUERY_STRING} [L]

$app->map("/$V/tables/:table/rows", function ($table) use ($db, $params, $data, $app) {
    $id = null;
    switch($app->request()->getMethod()) {
        // POST one new table entry
        case 'POST':
            $id = $db->set_entry_relational($table, $data);
            $params['id'] = $id;
            break;
        // PUT a change set of table entries
        case 'PUT':
            $db->set_entries($table, $data);
            break;
    }
    // GET all table entries
    $response = $db->get_entries($table, $params);
    JsonView::render($response);
})->via('GET', 'POST', 'PUT');

$app->map("/$V/tables/:table/rows/:id", function ($table, $id) use ($db, $params, $data, $app) {
    switch($app->request()->getMethod()) {
        // PUT an updated table entry
        case 'PUT':
            $db->set_entry_relational($table, $data);
            break;
        // DELETE a given table entry
        case 'DELETE':
            echo $db->delete($table, $id);
            return;
    }
    // GET a table entry
    $response = $db->get_entries($table, $params);
    JsonView::render($response);
})->via('DELETE', 'GET', 'PUT');

/**
 * GROUPS COLLECTION
 */

// RewriteRule ^1/groups/*$                api.php?api_collection=groups&%{QUERY_STRING} [L]
// RewriteRule ^1/groups/([^/]+)/*$            api.php?api_collection=groups&id=$1%{QUERY_STRING} [L]

/** (Optional slim route params break when these two routes are merged) */

$app->get("/$V/groups", function () use ($db, $params, $data, $app) {
    // @TODO need POST and PUT
    $response = $db->get_entries("directus_groups");
    JsonView::render($response);
});

$app->get("/$V/groups/:id", function ($id = null) use ($db, $params, $data, $app) {
    // @TODO need POST and PUT
    is_null($id) ? $id = 1 : null ; // Hardcoding ID temporarily
    $response = $db->get_group($id);
    JsonView::render($response);
});


/** MEDIA COLLECTION
 */

// RewriteRule ^1/media/*$                 api.php?api_collection=media&api_response_type=json&%{QUERY_STRING} [L]
// RewriteRule ^1/media/([^/]+)/*$             api.php?api_collection=media&api_response_type=json&id=$1&%{QUERY_STRING} [L]

$app->map("/$V/media(/:id)", function ($id = null) use ($db, $params, $data, $app) {

    // A URL is specified. Upload the file
    if (isset($data['url']) && $data['url'] != "") {
        $media = new Media($data['url'], RESOURCES_PATH);
        $media_data = $media->data();
        $data['type'] = $media_data['type'];
        $data['charset'] = $media_data['charset'];
        $data['size'] = $media_data['size'];
        $data['width'] = $media_data['width'];
        $data['height'] = $media_data['height'];
        $data['name'] = $media_data['name'];
        $data['date_uploaded'] = $media_data['date_uploaded'];
        if (isset($media_data['embed_id'])) {
            $data['embed_id'] = $media_data['embed_id'];
        }
    }

    if (isset($data['url']))
        unset($data['url']);

    $table = "directus_media";
    switch ($app->request()->getMethod()) {
        case "POST":
            $data['date_uploaded'] = gmdate('Y-m-d H:i:s');
            $params['id'] = $db->set_media($data);
            break;
        case "PATCH":
            $data['id'] = $id;
        case "PUT":
            if (!isset($id)) {
                $db->set_entries($table, $data);
                break;
            }
            $db->set_media($data);
            break;
    }

    $response = $db->get_entries($table, $params);
    JsonView::render($response);
})->via('GET','PATCH','POST','PUT');


/** PREFERENCES COLLECTION
 */

// RewriteRule ^1/tables/([^/]+)/preferences/*$       api.php?api_collection=preferences&api_response_type=json&table_name=$1&%{QUERY_STRING} [L]

$app->map("/$V/tables/:table/preferences", function($table) use ($db, $params, $data, $app) {
    $params['table_name'] = $table;
    switch ($app->request()->getMethod()) {
        case "PUT":
            //This data should not be hardcoded.
            $id = $data['id'];
            $db->set_entry('directus_preferences', $data);
            //$db->insert_entry($table, $data, $id);
            break;
        case "POST":
            // This should not be hardcoded, needs to be corrected
            $data['user'] = 1;
            $id = $db->insert_entry($table, $data);
            $params['id'] = $id;
            break;
    }
    $response = $db->get_table_preferences($table);
    JsonView::render($response);
})->via('GET','POST','PUT');


/** REVISIONS COLLECTION
 */

// RewriteRule ^1/tables/([^/]+)/rows/([^/]+)/revisions/*$ api.php?api_collection=revisions&api_response_type=json&table_name=$1&id=$2&%{QUERY_STRING} [L]

$app->get("/$V/tables/:table/rows/:id/revisions", function($table, $id) use ($db, $params, $data, $app) {
    $params['table_name'] = $table;
    $params['id'] = $id;
    $response = $db->get_revisions($params);
    JsonView::render($response);
});


/** SETTINGS COLLECTION
 */

// RewriteRule ^1/settings*$                 api.php?api_collection=settings&%{QUERY_STRING} [L]
// RewriteRule ^1/settings/([^/]+)/*$            api.php?api_collection=settings&id=$1&%{QUERY_STRING} [L]

$app->map("/$V/settings(/:id)", function ($id = null) use ($db, $params, $data, $app) {
    switch ($http_method) {
        case "POST":
        case "PUT":
            $db->set_settings($data);
            break;
    }
    $all_settings = $db->get_settings('global');
    $response = is_null($id) ? $all_settings : $result[$id];
    JsonView::render($response);
})->via('GET','POST','PUT');

/** TABLES COLLECTION
 */

// RewriteRule ^1/tables/*$                api.php?api_collection=tables&api_response_type=json&%{QUERY_STRING} [L]
// RewriteRule ^1/tables/([^/]+)/*$            api.php?api_collection=table&api_response_type=json&table_name=$1&%{QUERY_STRING} [L]

// GET table index
$app->get("/$V/tables", function () use ($db, $params, $data) {
    $response = $db->get_tables($params);
    JsonView::render($response);
})->name('table_index');

// GET and PUT table details
$app->map("/$V/tables/:table", function ($table) use ($db, $params, $data, $app) {
    /* PUT updates the table */
    if($app->request()->isPut()) {
        $db->set_table_settings($data);
    }
    $response = $db->get_table_info($table, $params);
    JsonView::render($response);
})->via('GET', 'PUT')->name('table_detail');


/** UPLOAD COLLECTION
 */

// RewriteRule ^1/upload*$                 api.php?api_collection=upload [L]

$app->post("/$V/upload", function () use ($db, $params, $data, $app) {
    $result = array();
    foreach ($_FILES as $file) {
      $media = new Media($file, RESOURCES_PATH);
      array_push($result, $media->data());
    }
    JsonView::render($result);
});


/** USERS COLLECTION
 */

// RewriteRule ^1/users/*$                 api.php?api_collection=users&%{QUERY_STRING} [L]
// RewriteRule ^1/users/([^/]+)/*$             api.php?api_collection=users&id=$1&%{QUERY_STRING} [L]
// RewriteRule ^1/tables/directus_users/rows/*$      api.php?api_collection=users&%{QUERY_STRING} [L]
// RewriteRule ^1/tables/directus_users/rows/([^/]+)/*$  api.php?api_collection=users&id=$1&%{QUERY_STRING} [L]

// GET user index
$app->get("/$V/users", function () use ($db, $params, $data) {
    $users = Users::getAllWithGravatar();
    // foreach ($users['rows'] as &$user) {
    //     $user['avatar'] = get_gravatar($user['email'], 28, 'identicon');
    // }
    JsonView::render($users);
})->name('user_index');

// POST new user
$app->post("/$V/users", function() use ($db, $params, $data) {
    $table = 'directus_users';
    $id = $db->set_entries($table, $params);
    $params['id'] = $id;
    $response = $db->get_entries($table, $params);
    JsonView::render($response);
})->name('user_post');

// GET or PUT a given user
$app->map("/$V/users/:id", function ($id) use ($db, $params, $data, $app) {
    $table = 'directus_users';
    $params['id'] = $id;
    if($app->request()->isPut()) {
        $db->set_entry($table, $data);
    }
    $response = $db->get_entries($table, $params);
    JsonView::render($response);
})->via('GET', 'PUT');


/** UI COLLECTION
 */

// RewriteRule ^1/tables/([^/]+)/ui/*$
//  api.php?api_collection=ui&api_response_type=json&table_name=$1&%{QUERY_STRING} [L]

$app->map("/$V/tables/:table/ui", function($table) use ($db, $params, $data, $app) {
    switch ($app->request()->getMethod()) {
      case "PUT":
      case "POST":
        $db->set_ui_options($data, $table, $params['column_name'], $params['ui_name']);
        break;
    }
    $response = $db->get_ui_options($table, $params['column_name'], $params['ui_name']);
    JsonView::render($response);
})->via('GET','POST','PUT');


/**
 * Run the Router
 */

if($_GET['run_api_router']) {
    try {
        header("Content-Type: application/json; charset=utf-8");
        // Run Slim
        $app->run();
        // var_dump($app->request());
        // exit;
    } catch (DirectusException $e){
        switch ($e->getCode()) {
            case 404:
                header("HTTP/1.0 404 Not Found");
                echo json_encode($e->getMessage());
                break;
        }
    } catch (Exception $e) {
        header("HTTP/1.1 500 Internal Server Error");
        //echo format_json(json_encode($e));
        echo print_r($e, true);
        //echo $e->getCode();
        //echo $e->getMessage();
    }
}