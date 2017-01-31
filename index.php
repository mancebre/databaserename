<?php

$link = mysql_connect('localhost', 'guidel', 'Groml75');
$res = mysql_query("SHOW DATABASES");

$databases =array();

$drupal = false;
$moodle = false;
$currentDatabases = readDatabaseName();

if($currentDatabases) {
    $drupal = $currentDatabases['drupal'];
    $moodle = $currentDatabases['moodle'];
}

// Get list of all databases
while ($row = mysql_fetch_assoc($res)) {
    if(ifWeNeedThisDatabase($row['Database'], $drupal)) {
        $databases[] = $row['Database'];
    }
}
/**
 * @param $database
 * @return bool
 */
function ifWeNeedThisDatabase($database, $drupal)
{
    // Array of databases we don't need here.
    $doNotUse = array('mysql', 'information_schema', 'guidel_drupal', 'guidel_moodle', 'performance_schema', 'phpmyadmin', 'redmine');

    if($drupal) {
        $doNotUse[] = $drupal;
    }

    if(in_array($database, $doNotUse)) {
        return false;
    } elseif (strpos($database, 'moodle') !== false) {
        return false;
    } else {
        return true;
    }

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

?>

<!DOCTYPE html>
<html>
<head>
    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">

    <!-- Optional theme -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">
</head>
<body onload="onPageLoad();">

<style>
    body #loader {
        display: none;
        position: fixed;
        left: 0;
        top: 0;
        z-index: 999;
        width: 100%;
        height: 100%;
        overflow: visible;
        opacity: 0.7;
        background: #333 url("loading.gif") no-repeat center center; }
    div {
        font-size: 26px;
        font-weight: 600;
        text-align: center;
    }
</style>

<div id="loader"></div>

<?php if($currentDatabases) {?>
    <div id="databases">
        <p>Current drupal database is: <?php echo $drupal?></p>
        <p>Current moodle database is: <?php echo $moodle?></p>
    </div>
<?php } ?>

<div>
    <select id="database">
        <option value="0">Select database</option>
        <?php foreach ($databases as $base) { ?>
            <option value="<?php echo $base?>"><?php echo $base?></option>
        <?php } ?>
    </select>

    <button onclick="renameDatabase();">Import</button>
</div>

<div id="result"></div>

<script>
    /**
     *
     * @returns {boolean}
     */
    function renameDatabase() {

        var database = document.getElementById('database').value;

        if(database === '0') {
            document.getElementById("result").innerHTML = "SELECT DATABASE!!!";
            return false;
        }

        if(confirm("This will take a while. Keep calm and wait no matter what. do not close browser and do not refresh the page!!")) {

            document.getElementById('loader').style.display = 'block';

            var startTime = new Date().getTime();

            var http = new XMLHttpRequest();
            var url = "rename_database.php";
            var params = "database=" + database;
            http.open("POST", url, true);

            //Send the proper header information along with the request
            http.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

            http.onreadystatechange = function() {//Call a function when the state changes.
                if(http.readyState == 4) {
                    document.getElementById('loader').style.display = 'none';
                    var requestTime = new Date().getTime() - startTime,
                        min = (requestTime/1000/60) << 0,
                        sec = (requestTime/1000) % 60;

                    var RequestTime = { 'executionTime': min + ':' + sec };

                    // Put the object into storage
                    localStorage.setItem('testObject', JSON.stringify(RequestTime));
                }
                if(http.readyState == 4 && http.status == 200) {
//                    document.getElementById("result").innerHTML = this.responseText;
                    alert(this.responseText);
                    window.location.reload(false);
                } else if(http.readyState == 4 && http.status != 200) {
//                    document.getElementById("result").innerHTML = this.responseText;
                    alert(this.responseText);
                    console.log('ERROR', this);
//                    window.location.reload(false);
                }
            };
            http.send(params);
        }
    }

    function onPageLoad() {

        // Retrieve the object from storage
        var RequestTime = localStorage.getItem('testObject');

        if(RequestTime) {
            console.log('Last rename execution time: ', JSON.parse(RequestTime));
        }
    }
</script>

</body>
</html>
