<?php

require_once "Database.php";

if(isset($_POST['database'])) {
    $database = trim($_POST['database']);
    if(strlen($database) === 0) {
        http_response_code(404);
        exit;
    } else {

//        DB::changeDBName('guidel_drupal_learn');
//        $stmt = DB::run("SELECT * FROM users");
//        while ($row = $stmt->fetch(PDO::FETCH_LAZY))
//        {
//            echo $row['name'],",";
//            echo $row->name,",";
//            echo $row[1], PHP_EOL;
//        }

        init($database);
//        sleep(30);
    }
} elseif (isset($_POST['reset'])) {
    emptyDataFile();
    echo "system reset, you can now rename database";
    exit;
} else {
    http_response_code(404);
    exit;
}

/**
 * @param $database
 */
function init($database)
{
    $drupal = $database;
    $moodle = str_replace('drupal', 'moodle', $database);

    removeWickedDatabases();

    // Remove databases with char - because they make troubles with this code.

    // Proveri dali ima imena u data.json
    $data = readDatabaseName();
    // Ako ima preimenuj baze guidel_drupal i guidel_moodle u ove iz data.json

    if($data) {
        if(!renameDatabase('guidel_drupal', $data['drupal']) || !renameDatabase('guidel_moodle', $data['moodle'])) {
            http_response_code(500);
            echo "re rename database error";
            exit;
        }
    }

    if(!DropDatabases()) {
        http_response_code(500);
        echo "Create database error";
        exit;
    }

    // Sacuvaj imena od novih izabranih baza
    saveDatabaseName($drupal, $moodle);

    // Preimenuj izabrane baze u guidel_drupal i guidel_moodle
    if(!renameDatabase($drupal, 'guidel_drupal') || !renameDatabase($moodle, 'guidel_moodle')) {
        http_response_code(500);
        echo "Rename database error";
        exit;
    }

    if(!updatePass($drupal))
    {
        http_response_code(500);
        echo "Users password update fail!";
        exit;
    }

    echo "Databases are renamed. All passwords are now 123456";
}

/**
 * Remove all tables with char "-"
 *
 * @return bool
 */
function removeWickedDatabases() {
    $dbhost = 'localhost';
    $dbuser = 'guidel';
    $dbpass = 'Groml75';
    $conn = mysql_connect($dbhost, $dbuser, $dbpass);

    if(! $conn ) {
        die('Could not connect: ' . mysql_error());
    }

    $sql = "
SELECT 
    table_schema AS db, table_name AS tbl
FROM
    information_schema.tables
WHERE
    table_name LIKE '%-%';
    ";

    $retval = mysql_query( $sql, $conn );

    while($row = mysql_fetch_array($retval)) {
        $dropQuery = "DROP TABLE " . $row['db'] . ".`" . $row['tbl'] . "`;";
        mysql_query( $dropQuery, $conn );
    }

    mysql_close($conn);

    return true;
}

/**
 * Truncate databases
 *
 * @return bool
 */
function DropDatabases() {
    // Drop old database
    $dbhost = 'localhost';
    $dbuser = 'guidel';
    $dbpass = 'Groml75';
    $conn = mysql_connect($dbhost, $dbuser, $dbpass);

    if(! $conn ) {
        die('Could not connect: ' . mysql_error());
    }

    mysql_query( "set foreign_key_checks=0", $conn );

    $sql = 'DROP DATABASE IF EXISTS guidel_drupal';
    $retval = mysql_query( $sql, $conn );

    if(! $retval ) {
        plog('DROP guidel DATABASE fail');
        return false;
    }

    $sql = 'DROP DATABASE IF EXISTS guidel_moodle';
    $retval = mysql_query( $sql, $conn );

    if(! $retval ) {
        plog('DROP moodle DATABASE fail');
        return false;
    }

    // Create new database
    $sql = 'CREATE DATABASE guidel_drupal';
    $retval = mysql_query( $sql, $conn );

    if(! $retval ) {
        plog('CREATE DATABASE fail');
        return false;
    }
    $sql = 'CREATE DATABASE guidel_moodle';
    $retval = mysql_query( $sql, $conn );

    if(! $retval ) {
        plog('CREATE DATABASE fail');
        return false;
    }

    mysql_query( "set foreign_key_checks=1", $conn );

    mysql_close($conn);

    return true;
}

/**
 * @param $drupal
 * @param $moodle
 */
function saveDatabaseName($drupal, $moodle)
{
    $data = array(
        'drupal' => $drupal,
        'moodle' => $moodle
    );
    $fp = fopen('data.json', 'w');
    fwrite($fp, json_encode($data));
    fclose($fp);
}

/**
 * @return bool|mixed
 */
function readDatabaseName()
{
    $string = file_get_contents("data.json");
    $data = json_decode($string, true);

    if(isset($data['drupal']) && isset($data['moodle'])) {
        return $data;
    } else {
        return false;
    }
}

/**
 * @param $database
 * @return bool
 */
function updatePass($database)
{
    $dbhost = 'localhost';
    $dbuser = 'guidel';
    $dbpass = 'Groml75';
    $conn = mysql_connect($dbhost, $dbuser, $dbpass);

    if(! $conn ) {
        die('Could not connect: ' . mysql_error());
    }

    $sql = "UPDATE guidel_drupal.users SET pass = 'e10adc3949ba59abbe56e057f20f883e' WHERE pass IS NOT NULL;";
    $retval = mysql_query( $sql, $conn );

    if(! $retval ) {
        plog('password update fail!');
        return false;
    }

    mysql_close($conn);

    return true;
}

/**
 * @param $oldDb
 * @param $newDatabase
 * @return bool
 */
function renameDatabase($oldDb, $newDatabase)
{
    $command = 'for table in `mysql -uguidel -pGroml75 -s -N -e "use '.$oldDb.';show tables from '.$oldDb.';"`; do mysql -uguidel -pGroml75 -s -N -e "use '.$oldDb.';rename table '.$oldDb.'.$table to '.$newDatabase.'.$table;"; done;';
//    $command = 'for table in `mysql -uguidel -pGroml75 -s -N -e "use guidel_moodle_learn_new;show tables from guidel_moodle_learn_new;"`; do mysql  -uguidel -pGroml75 -s -N -e "use guidel_moodle_learn_new;rename table guidel_moodle_learn_new.$table to new_db.$table;"; done;';
    exec($command, $result, $output);

    if($output != 0) {
        plog($result, 'Copy '.$newDatabase.' DATABASE fail ' . $command);
        echo $command;
        return false;
    }

    return true;
}

/**
 * Delete everything from folder
 *
 * @param $directory
 */
function recursiveRemoveDirectory($directory)
{
    foreach(glob("{$directory}/*") as $file)
    {
        if(is_dir($file)) {
            recursiveRemoveDirectory($file);
        } else {
            unlink($file);
        }
    }
}

/**
 * @param $var
 * @param string $description
 * @param string $file
 */
function plog($var, $description = '', $file = 'errors.log')
{
    error_log($description .' ' . print_r($var)."\n", 3, $file);
}

/**
 * @param $drupal
 * @param $moodle
 */
function copyDatabase($drupal, $moodle)
{
    $command = 'mysqldump -uguidel -pGroml75 -v '.$drupal.' | mysql -uguidel -pGroml75 -D guidel_drupal';
    exec($command, $result, $output);

    $command = 'mysqldump -uguidel -pGroml75 -v '.$moodle.' | mysql -uguidel -pGroml75 -D guidel_moodle';
    exec($command, $result, $output);
}

function emptyDataFile()
{
    $f = @fopen("data.json", "r+");
    if ($f !== false) {
        ftruncate($f, 0);
        fclose($f);
    }
}