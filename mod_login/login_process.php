<?php

$db = MySQL::getInstance();

if (isset($_POST['username'], $_POST['p'])) {
    $username = $_POST['username'];
    //filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['p']; // The hashed password.
    
    if (login($username, $password, $db) == true) {
        // Login success 
       redirect("home.php","");
    } else {
        // Login failed 
       redirect("index.php","Failed!");
    }
} else {
    // The correct POST variables were not sent to this page. 
    redirect("index.php","Failed!");
}

function login($username, $password, $db) {
    // Using prepared statements means that SQL injection is not possible.
    if ($stmt = $db->prepare("SELECT aum_seq_no, aum_username, aum_password, aum_salt
				  FROM apps_user_main
                                  WHERE aum_username = ? LIMIT 1")) {
        $stmt->bind_param('s', $username);  // Bind "$email" to parameter.
        $stmt->execute();    // Execute the prepared query.
        $stmt->store_result();

        // get variables from result.
        $stmt->bind_result($user_id, $username, $db_password, $salt);
        $stmt->fetch();

        // hash the password with the unique salt.
        $password = hash('sha512', $password . $salt);
        if ($stmt->num_rows == 1) {
            // If the user exists we check if the account is locked
            // from too many login attempts
            if (checkbrute($user_id, $db) == true) {
                // Account is locked
                // Send an email to user saying their account is locked
                return false;
            } else {
                // Check if the password in the database matches
                // the password the user submitted.
                if ($db_password == $password) {
                    // Password is correct!
                    // Get the user-agent string of the user.
                    $user_browser = $_SERVER['HTTP_USER_AGENT'];

                    // XSS protection as we might print this value
                    //$user_id = preg_replace("/[^0-9]+/", "", $user_id);
                
                    // XSS protection as we might print this value
                    $username = preg_replace("/[^a-zA-Z0-9_\-]+/", "", $username);

                    $_SESSION['username'] = $username;

                    $_SESSION['login_string'] = hash('sha512', $password . $user_browser);

                    // Login successful.
                    return true;
                } else {
                    // Password is not correct
                    // We record this attempt in the database
                    $now = time();
                    if (!$db->query("INSERT INTO apps_login_attempts(ala_user_id, ala_time)
                                    VALUES ('$user_id', '$now')")) {
                        redirect("index.php","Failed!");
                    }

                    return false;
                }
            }
        } else {
            // No user exists.
            return false;
        }
    } else {
        // Could not create a prepared statement
        redirect("index.php","Failed!");
    }
}

function checkbrute($user_id, $db) {
    // Get timestamp of current time
    $now = time();

    // All login attempts are counted from the past 2 hours.
    $valid_attempts = $now - (2 * 60 * 60);

    if ($stmt = $db->prepare("SELECT ala_time
                                  FROM apps_login_attempts
                                  WHERE ala_user_id = ? AND ala_time > '$valid_attempts'")) {
        $stmt->bind_param('i', $user_id);

        // Execute the prepared query.
        $stmt->execute();
        $stmt->store_result();

        // If there have been more than 5 failed logins
        if ($stmt->num_rows > 5) {
            return true;
        } else {
            return false;
        }
    } else {
        // Could not create a prepared statement
        redirect("index.php","Failed!");
    }
}
?>