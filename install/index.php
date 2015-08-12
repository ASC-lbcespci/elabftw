<?php
/********************************************************************************
*                                                                               *
*   Copyright 2012 Nicolas CARPi (nicolas.carpi@gmail.com)                      *
*   http://www.elabftw.net/                                                     *
*                                                                               *
********************************************************************************/

/********************************************************************************
*  This file is part of eLabFTW.                                                *
*                                                                               *
*    eLabFTW is free software: you can redistribute it and/or modify            *
*    it under the terms of the GNU Affero General Public License as             *
*    published by the Free Software Foundation, either version 3 of             *
*    the License, or (at your option) any later version.                        *
*                                                                               *
*    eLabFTW is distributed in the hope that it will be useful,                 *
*    but WITHOUT ANY WARRANTY; without even the implied                         *
*    warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR                    *
*    PURPOSE.  See the GNU Affero General Public License for more details.      *
*                                                                               *
*    You should have received a copy of the GNU Affero General Public           *
*    License along with eLabFTW.  If not, see <http://www.gnu.org/licenses/>.   *
*                                                                               *
********************************************************************************/
/* install/index.php to get an installation up and running */
session_start();
require_once '../vendor/autoload.php';
require_once '../inc/functions.php';
$errflag = false;
?>

<!DOCTYPE HTML>
<html>
<head>
<meta http-equiv="Content-type" content="text/html;charset=UTF-8" />
<link rel="icon" type="image/ico" href="../img/favicon.ico" />
<title>eLabFTW - INSTALL</title>
<meta name="author" content="Nicolas CARPi" />
<!-- CSS -->
<!-- Bootstrap -->
<link rel="stylesheet" media="all" href="../js/bootstrap/dist/css/bootstrap.min.css">
<link rel="stylesheet" media="all" href="../css/main.css" />
<link rel="stylesheet" media="all" href="../js/jquery-ui/themes/smoothness/jquery-ui.min.css" />
<style>
/* little gray text */
.install_hint {
    color:gray;
    font-size:12px;
    display:inline;
}
</style>

<!-- JAVASCRIPT -->
<script src="../js/jquery/dist/jquery.min.js"></script>
<script src="../js/jquery-ui/jquery-ui.min.js"></script>
</head>

<body>
<section id="container">
<section class='box'>
<center><img src='../img/logo.png' alt='elabftw' title='elabftw' /></center>
<h2>Welcome to the install of eLabFTW</h2>

<?php
// Check if there is already a config file

if (file_exists('../config.php')) {
    // ok there is a config file, but maybe it's a fresh install, so redirect to the register page
    // check that the config file is here and readable
    if (!is_readable('../config.php')) {
        $message = "No readable config file found. Make sure the server has permissions to read it. Try :<br />
            chmod 644 config.php<br />";
        display_message('error_nocross', $message);
        custom_die();
    }

    // check if there are users registered
    require_once '../config.php';
    try {
        $pdo_options = array();
        $pdo_options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASSWORD, $pdo_options);
    } catch (Exception $e) {
        die('Error : ' . $e->getMessage());
    }
    // ok so we are connected, now count the number of tables before trying to count the users
    // if we are in docker, the number of tables might be 0
    // so we will need to import the structure before going further
    $sql = "SELECT COUNT(DISTINCT `table_name`) FROM `information_schema`.`columns` WHERE `table_schema` = :db_name";
    $req = $pdo->prepare($sql);
    $req->bindValue(':db_name', DB_NAME);
    $req->execute();
    $res = $req->fetch();
    if ($res[0] < 2) {
        import_sql_structure();
        header('Location: ../register.php');
        exit;
    }

    $sql = "SELECT * FROM users";
    $req = $pdo->prepare($sql);
    $req->execute();
    $users_count = $req->rowCount();
    // redirect to register page if no users are in the database
    if ($users_count === 0) {
        header('Location: ../register.php');
    } else {
        $message = 'It looks like eLabFTW is already installed. Delete the config file if you wish to reinstall it.';
        display_message('error_nocross', $message);
        custom_die();
    }
}
?>

<h3>Preliminary checks</h3>
<?php
// CHECK WE ARE WITH HTTPS
if (!\Elabftw\Elabftw\Tools::usingSsl()) {
    // get the url to display a link to click (without the port)
    $url = 'https://' . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'];
    $message = "eLabFTW works only in HTTPS. Please enable HTTPS on your server. Or click this link : <a href='$url'>$url</a>";
    display_message('error_nocross', $message);
    custom_die();
}

// CHECK PHP version
if (!function_exists('version_compare') || version_compare(PHP_VERSION, '5.5', '<')) {
    $message = "Your version of PHP isn't recent enough. Please update your php version to at least 5.5";
    display_message('error_nocross', $message);
    $errflag = true;
}

// Check for hash function
if (!function_exists('hash')) {
    $message = "You don't have the hash function. On Freebsd it's in /usr/ports/security/php5-hash.";
    display_message('error_nocross', $message);
    custom_die();
}

// UPLOADS DIR
if (!is_writable('../uploads') || !is_writable('../uploads/tmp')) {
    // create the folders
    mkdir('../uploads');
    mkdir('../uploads/tmp');
    // check the folders
    if (is_writable('../uploads') && is_writable('../uploads/tmp')) {
        $message = "The <em>uploads/</em> folder and its subdirectory were created successfully.";
        display_message('info_nocross', $message);
    } else { // failed at creating the folder
        $message = "Faild creating <em>uploads/</em> directory. You need to do it manually. 
            <a href='../doc/_build/html/common-errors.html#failed-creating-uploads-directory'>Click here to discover how.</a>";
        display_message('error_nocross', $message);
        $errflag = true;
    }
}

// Check for required php extensions
$extensionArr = array('curl', 'gettext', 'gd', 'openssl', 'mbstring');
foreach ($extensionArr as $ext) {
    if (!extension_loaded($ext)) {
        $message = "The <em>" . $ext . "</em> extension is <strong>NOT</strong> loaded.
                <a href='../doc/_build/html/common-errors.html#extension-is-not-loaded'>Click here to read how to fix this.</a>";
        display_message('error_nocross', $message);
        $errflag = true;
    }
}
// we die here and not before so we have display of several errors
if ($errflag) {
    custom_die();
} else {
    $message = 'Everything is good on your server. You can install eLabFTW :)';
    display_message('info_nocross', $message);
}

?>
<h3>Configuration</h3>

<!-- MYSQL -->
<form action='install.php' method='post'>
<fieldset>
<legend><strong>MySQL</strong></legend>
<p>MySQL is the database that will store everything. eLabFTW need to connect to it with a username/password. This is <strong>NOT</strong> your account with which you'll use eLabFTW. If you followed the installation instructions, you should have created a database <em>elabftw</em> with a user <em>elabftw</em> that have all the rights on it.</p>

<p>
<label for='db_host'>Host for mysql database:</label><br />
<input id='db_host' name='db_host' type='text' value='localhost' />
<span class='install_hint'>(you can safely leave 'localhost' here)</span>
</p>

<p>
<label for='db_name'>Name of the database:</label><br />
<input id='db_name' name='db_name' type='text' value='elabftw' />
<span class='install_hint'>(should be 'elabftw' if you followed the instructions)</span>
</p>

<p>
<label for='db_user'>Username to connect to the MySQL server:</label><br />
<input id='db_user' name='db_user' type='text' value='
<?php
// we show root here if we're on windoze or Mac OS X
if (PHP_OS == 'WINNT' || PHP_OS == 'WIN32' || PHP_OS == 'WINNT' || PHP_OS == 'Windows' || PHP_OS == 'Darwin') {
    echo 'root';
} else {
    echo 'elabftw';
}
?>' />
<span class='install_hint'>(should be 'elabftw' or 'root' if you're on Mac/Windows)</span>
</p>

<p>
<label for='db_password'>Password:</label><br />
<input id='db_password' name='db_password' type='password' />
<span class='install_hint'>(should be a very complicated one that you won't have to remember)</span>
</p>

<div class='center' style='margin-top:8px'>
<button type='button' id='test_sql_button' class='button'>Test MySQL connection to continue</button>
</div>

</fieldset>

<br />

<!-- FINAL SECTION -->
<section id='final_section'>
<p>When you click the button below, it will create the file <em>config.php</em>. If it cannot create it (because the server doesn't have write permission to this folder), your browser will download it and you will need to put it in the main elabftw folder.</p>
<p>To put this file on the server, you can use scp (don't write the '$') :</p>
<code>$ scp /path/to/config.php pi@12.34.56.78:/var/www/elabftw/</code>
<p>If you want to modify some parameters afterwards, just edit this file directly.</p>

<div class='center' style='margin-top:8px'>
    <button type="submit" name="Submit" class='button'>INSTALL eLabFTW</button>
</div>

<p>If the config.php file is in place, <button onclick='window.location.reload()'>reload this page</button></p>
<p>You will be redirected to the registration page, where you can get your admin account :)</p>
</section>

</form>

</section>

</section>

<script>
$(document).ready(function() {
    // hide the install button
    $('#final_section').hide();

    // sql test button
    $('#test_sql_button').click(function() {
        var mysql_host = $('#db_host').val();
        var mysql_name = $('#db_name').val();
        var mysql_user = $('#db_user').val();
        var mysql_password = $('#db_password').val();

        $.post('test.php', {
            mysql: 1,
            db_host: mysql_host,
            db_name: mysql_name,
            db_user: mysql_user,
            db_password: mysql_password
        }).done(function(test_result) {
            if (test_result == 1) {
                alert('MySQL connection was successful ! :)');
                $('#test_sql_button').hide();
                $('#final_section').show();
            } else {
                alert('The connection failed with this error : ' + test_result);
            }
        });
    });
});
</script>
</body>
</html>

