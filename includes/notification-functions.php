<?php
//this file creates notifications safely without breaking pages
    
    //checking if notifications table exists without crashing website
function notificationTableExists($conn){
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
    return ($result && mysqli_num_rows($result) > 0);
}

//checking if notification column exists
function notificationColumnExists($conn, $column){

    $column = mysqli_real_escape_string($conn, $column);

    $result = mysqli_query($conn, "
    SHOW COLUMNS FROM notifications LIKE '$column'
    ");

    return ($result && mysqli_num_rows($result) > 0);
}

function createNotification($conn, $user_id, $title, $message, $link = ""){

    if(!notificationTableExists($conn)){
        return true;
    }

    $user_id = intval($user_id);
    $title = mysqli_real_escape_string($conn, $title);
    $message = mysqli_real_escape_string($conn, $message);
    $link = mysqli_real_escape_string($conn, $link);

    $columns = [];
    $values = [];

    if(notificationColumnExists($conn, "user_id")){
        $columns[] = "user_id";
        $values[] = "'$user_id'";
    }

    if(notificationColumnExists($conn, "title")){
        $columns[] = "title";
        $values[] = "'$title'";
    }

    if(notificationColumnExists($conn, "message")){
        $columns[] = "message";
        $values[] = "'$message'";
    }

    if(notificationColumnExists($conn, "link")){
        $columns[] = "link";
        $values[] = "'$link'";
    }

    if(notificationColumnExists($conn, "is_read")){
        $columns[] = "is_read";
        $values[] = "'No'";
    }

    if(notificationColumnExists($conn, "created_at")){
        $columns[] = "created_at";
        $values[] = "NOW()";
    }

    if(count($columns) == 0){
        return true;
    }

    $column_sql = implode(", ", $columns);
    $value_sql = implode(", ", $values);

    mysqli_query($conn, "
    INSERT INTO notifications($column_sql)
    VALUES($value_sql)
    ");

    return true;
}

?>