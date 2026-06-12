<?php

function adminNotificationTableExists($conn){
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'admin_notifications'");
    return ($result && mysqli_num_rows($result) > 0);
}

function createAdminNotification($conn, $admin_id, $title, $message, $link = ""){

    if(!adminNotificationTableExists($conn)){
        return true;
    }

    $admin_id = intval($admin_id);
    $title = mysqli_real_escape_string($conn, $title);
    $message = mysqli_real_escape_string($conn, $message);
    $link = mysqli_real_escape_string($conn, $link);

    mysqli_query($conn, "
    INSERT INTO admin_notifications(
        admin_id,
        title,
        message,
        link,
        is_read,
        created_at
    )
    VALUES(
        '$admin_id',
        '$title',
        '$message',
        '$link',
        'No',
        NOW()
    )
    ");

    return true;
}

function notifyAdminsByRoles($conn, $roles, $title, $message, $link = ""){

    if(!is_array($roles) || count($roles) == 0){
        return true;
    }

    $safe_roles = [];

    foreach($roles as $role){
        $safe_roles[] = "'" . mysqli_real_escape_string($conn, $role) . "'";
    }

    $roles_sql = implode(",", $safe_roles);

    $admins_query = "
    SELECT admin_id
    FROM admin_users
    WHERE role IN ($roles_sql)
    ";

    $admins_result = mysqli_query($conn, $admins_query);

    if($admins_result && mysqli_num_rows($admins_result) > 0){

        while($admin = mysqli_fetch_assoc($admins_result)){

            createAdminNotification(
                $conn,
                $admin['admin_id'],
                $title,
                $message,
                $link
            );

        }

    }

    return true;
}

?>