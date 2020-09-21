<?php

header('content-type: application/json');
$db = mysqli_connect('127.0.0.1', 'root', '', 'schat')
    or die('DB error');

$result = [];
$maxMessageCount = 250;

calculateOnlineUsers($db);

/* Все функции */

/**
 * При любом запросе к API проверяем,
 * забанен ли пользователь.
 * Если забанен, то игнорируем его запрос.
 * Окно с баном показывает front-end.
 * Это лишь защитная функция от любителей редактировать код.
 *
 * При регистрации проверять бан не нужно!
 * Инфо о бане дается после авторизации при запросе get_user_data
 */
if(isset($_POST['action']) && $_POST['action'] != "register") {
    if ($_POST['token'] != null) {
        $tokenCheck = $_POST['token'];
        $reqCheck = "SELECT * FROM users WHERE token = '{$tokenCheck}'";
        $queryCheck = mysqli_query($db, $reqCheck)
        OR die('Нет пользователя с таким токеном!');
        $userCheck = mysqli_fetch_assoc($queryCheck);

        if ($userCheck == null) return;
        else if ($userCheck['banReason'] != null) {
            $result = ['error' => 'ban ' . $userCheck['banReason']];
            echo json_encode($result);
            return;
        }
    }
}

function cleanOldMessages($db, $maxMessageCount) {
    $msgCount = mysqli_fetch_assoc(mysqli_query($db, "SELECT COUNT(id) FROM messages"));
    if ((int)$msgCount["COUNT(id)"] > $maxMessageCount) {
        $minId = mysqli_fetch_assoc(mysqli_query($db, "SELECT MIN(id) FROM messages"));
        mysqli_query($db, "DELETE FROM messages WHERE id = {$minId['MIN(id)']}");
    }
}
function dateDiff($date1, $date2) {
    $diff = strtotime($date2) - strtotime($date1);
    return abs($diff);
}

/**
 * Вычисляет пользователей онлайн и
 * добавляем их в таблицу usersOnline
 * @param $db
 */
function calculateOnlineUsers($db) {
    $dateTime = date('Y-m-d H:i:s');
    $req = "SELECT * FROM users";
    $query = mysqli_query($db, $req);
    $users = mysqli_fetch_all($query, MYSQLI_ASSOC);

    if($users != null) {
        mysqli_query($db, "DELETE FROM usersonline");
        foreach ($users as $user) {
            if ($user['lastOnline'] != null && $user['login'] != 'system') {
                if (dateDiff($dateTime, $user['lastOnline']) < 5) { //сколько секунд назад юзер был онлайн
                    mysqli_query($db, "INSERT INTO usersonline (id) VALUES ('{$user['id']}')");
                }
            }
        }
    }
}

/**
 * Запрос пользователя на список онлайн
 * пользователей
 */
if (isset($_POST['action']) && $_POST['action'] == 'getusersonline') {
    $query = mysqli_query($db, "SELECT uo.id id, usr.login login, usr.isAdmin isAdmin FROM usersonline uo INNER JOIN users usr ON uo.id = usr.id ORDER BY uo.id");
    $info = mysqli_fetch_all($query, MYSQLI_ASSOC);
    if($info != null) {
        $result = $info;
    }
}

/**
 * Отправка сообщения в чат
 */
if (isset($_POST['action']) && $_POST['action'] == 'sendmsg') {
    $dateTime = date('Y-m-d H:i:s');
    $senderID = mysqli_real_escape_string($db, $_POST['senderID']);
    $message = mysqli_real_escape_string($db, strip_tags($_POST['message']));
    if (!empty($message) || $message == '0') {
        $msgCount = mysqli_fetch_assoc(mysqli_query($db, "SELECT COUNT(id) FROM messages"));
        if ((int)$msgCount["COUNT(id)"] > $maxMessageCount) {
            $minId = mysqli_fetch_assoc(mysqli_query($db, "SELECT MIN(id) FROM messages"));
            mysqli_query($db, "DELETE FROM messages WHERE id = {$minId['MIN(id)']}");
        }

        mysqli_query($db, "INSERT INTO messages (dateTime, senderID, message) VALUES ('{$dateTime}', '{$senderID}', '{$message}')")
            OR die('Ошибка отправки сообщения!');
    }
}

/**
 * Отправка изображения в чат
 */
if (isset($_POST['action']) && $_POST['action'] == 'sendimage') {
    $dateTime = date('Y-m-d H:i:s');
    $senderID = mysqli_real_escape_string($db, strip_tags($_POST['senderID']));
    $imageURL = mysqli_real_escape_string($db, strip_tags($_POST['imageURL']));
    if (!empty($imageURL)) {
        cleanOldMessages($db, $maxMessageCount);
        $imageCode = "<a href=" . $imageURL . "><img class=chatImage src=" . $imageURL . "></a>";
        $query = "INSERT INTO messages (dateTime, senderID, message) VALUES ('{$dateTime}', '{$senderID}', '{$imageCode}')";
        mysqli_query($db, $query)
            OR $result = ['error' => 'Ошибка отправки изображения!'];
    }
}

/**
 * Отправка handshake
 */

if (isset($_POST['action']) && $_POST['action'] == 'handshake') {
    $dateTime = date('Y-m-d H:i:s');
    $login = mysqli_real_escape_string($db, strip_tags($_POST['login']));
    if (!empty($login) && $login != 'system') {
        cleanOldMessages($db, $maxMessageCount);
        //senderid 1 - system
        $message = 'handshake ' . $login;
        mysqli_query($db, "INSERT INTO messages (dateTime, senderID, message) VALUES ('{$dateTime}', 1, '{$message}')")
            OR die('Ошибка отправки сообщения!');
    }
}

/**
 * Блокировка пользователя (только админы по токену)
 */
if (isset($_POST['action']) && $_POST['action'] == 'banuser') {
    $userToBan = mysqli_real_escape_string($db, strip_tags($_POST['userToBan']));
    $banReason = mysqli_real_escape_string($db, strip_tags($_POST['banReason']));
    $token = mysqli_real_escape_string($db, strip_tags($_POST['token']));

    if (!empty($userToBan) && !empty($token)) {
        $query = mysqli_query($db, "SELECT * FROM users WHERE token = '{$token}'");
        $user = mysqli_fetch_assoc($query);

        if ($user != null) {
            if ($user['isAdmin']) {
                mysqli_query($db, "UPDATE users SET banReason = '{$banReason}' WHERE login = '{$userToBan}'");

                $dateTime = date('Y-m-d H:i:s');
                $message = 'Администратор ' . $user['login'] . ' заблокировал ' . $userToBan . '. Причина: ' . $banReason;
                mysqli_query($db, "INSERT INTO messages (dateTime, senderID, message) VALUES ('{$dateTime}', 1, '{$message}')");
            } else {
                $result = ['error' => 'Недостаточно прав для блокировки пользователя!'];
            }
        }
    }
}

/**
 * Разблокировка пользователя (только админы по токену)
 */
if (isset($_POST['action']) && $_POST['action'] == 'unbanuser') {
    $userToUnban = mysqli_real_escape_string($db, strip_tags($_POST['userToUnban']));
    $token = mysqli_real_escape_string($db, strip_tags($_POST['token']));

    if (!empty($userToUnban) && !empty($token)) {
        $query = mysqli_query($db, "SELECT * FROM users WHERE token = '{$token}'");
        $user = mysqli_fetch_assoc($query);

        if ($user != null) {
            if ($user['isAdmin']) {
                mysqli_query($db, "UPDATE users SET banReason = NULL WHERE login = '{$userToUnban}'");

                $dateTime = date('Y-m-d H:i:s');
                $message = 'Администратор ' . $user['login'] . ' разблокировал ' . $userToUnban;
                mysqli_query($db, "INSERT INTO messages (dateTime, senderID, message) VALUES ('{$dateTime}', 1, '{$message}')");
            } else {
                $result = ['error' => 'Недостаточно прав для разблокировки пользователя!'];
            }
        }
    }
}

/**
 * Удаления сообщения (только админы по токену)
 */
if (isset($_POST['action']) && $_POST['action'] == 'deletemessage') {
    $id = mysqli_real_escape_string($db, strip_tags($_POST['id']));
    $token = mysqli_real_escape_string($db, strip_tags($_POST['token']));

    if (!empty($id) && !empty($token)) {
        $query = mysqli_query($db, "SELECT * FROM users WHERE token = '{$token}'")
            OR die('Нет пользователя с таким токеном!');
        $user = mysqli_fetch_assoc($query);

        if ($user != null) {
            if ($user['isAdmin']) {
                mysqli_query($db, "DELETE FROM messages WHERE id = '{$id}'");
            } else error_log("Попытка удаления сообщения не администратором! Пользователь " + $user['login']);
        }
    }
}

/**
 * Получение сообщений и фиксация онлайна пользователя,
 * который получает новые сообщения
 */
if (isset($_POST['action']) && $_POST['action'] == 'getmessages') {
    $getFrom = mysqli_real_escape_string($db, $_POST['getFrom']);
    $req = "SELECT ms.id id, ms.dateTime dateTime, usr.login senderLogin, ms.senderID senderID, ms.message message, usr.isAdmin isAdmin, usr.avatar avatar 
        FROM messages ms INNER JOIN users usr ON ms.senderID = usr.id WHERE ms.id >= '{$getFrom}'
        ORDER BY ms.id";
    $query = mysqli_query($db, $req);
    $info = mysqli_fetch_all($query, MYSQLI_ASSOC);
    if($info != null) {
        $result = $info;
    }

    /* фиксируем онлайн */
    $id = mysqli_real_escape_string($db, $_POST['id']);
    $dateTime = date('Y-m-d H:i:s');
    $req = "UPDATE users SET lastOnline = '{$dateTime}' WHERE id = '{$id}'";
    mysqli_query($db, $req);
}

/**
 * Авторизация
 */
if(isset($_POST['action']) && $_POST['action'] == 'authorize'){
    $login = mysqli_real_escape_string($db, $_POST['login']);
    $pass = mysqli_real_escape_string($db, $_POST['pass']);
    $user = mysqli_query($db, "SELECT * FROM users WHERE login='{$login}'");
    $userAssoc = mysqli_fetch_assoc($user);
    if($userAssoc != null && password_verify($_POST['pass'], $userAssoc['password'])){
        $token = bin2hex(random_bytes(10));
        mysqli_query($db,"UPDATE users SET token='{$token}' WHERE id='{$userAssoc['id']}';");
        $result = ['token' => $token];
    } else {
        $result = ['error' => 'Неверный логин или пароль'];
    }
}

/**
 * Деавторизация (удаление токена)
 */
if (isset($_POST['action']) && $_POST['action'] == 'logout') {
    $token = mysqli_real_escape_string($db, $_POST['token']);
    mysqli_query($db, "UPDATE users SET token=NULL WHERE token='{$token}'")
        OR $result = ['error' => 'Ошибка очистки токена'];
}

/**
 * Регистрация
 */
if(isset($_POST['action']) && $_POST['action'] == 'register'){
    $login = mysqli_real_escape_string($db, strip_tags($_POST['login']));
    $login = preg_replace('/\s/', '', $login);
    $pass = password_hash($_POST['pass'], PASSWORD_DEFAULT);
    $about = mysqli_real_escape_string($db, strip_tags($_POST['about']));
    $date = date('Y-m-d');

    $user = mysqli_query($db, "INSERT INTO users(login, password, isAdmin, about, dataReg) VALUES(
        '{$login}',
        '{$pass}',
        0,
        '{$about}',
        '{$date}'
    );");

    if (!$user) $result = ['error' => 'Ошибка регистрации: данный пользователь уже зарегистрирован!'];
    else $result = ['register' => 'Вы успешно зарегистрировались'];
}

/**
 * Получение информации пользователе по токену
 */
if(isset($_POST['action']) && $_POST['action'] == 'get_user_data'){
    if(!empty($_POST['token'])) {
        $token = mysqli_real_escape_string($db, $_POST['token']);
        $query = mysqli_query($db, "SELECT id, login, isAdmin, avatar, about, dataReg FROM users WHERE token='{$token}';");
        $info = mysqli_fetch_assoc($query);
        if($info != null) {
            $result = $info;
        } else {
            $result = ['error' => 'Неверный токен'];
        }
    }
}

/**
 * Получение информации пользователе по логину
 */
if(isset($_POST['action']) && $_POST['action'] == 'get_user_info'){
    if(!empty($_POST['login'])) {
        $login = mysqli_real_escape_string($db, $_POST['login']);
        $query = mysqli_query($db, "SELECT id, avatar, about, dataReg FROM users WHERE login='{$login}';");
        $info = mysqli_fetch_assoc($query);
        if($info != null) {
            $result = $info;
        } else {
            $result = ['error' => 'Пользователь не найден'];
        }
    }
}

/**
 * Получение страниц из базы
 */
if(isset($_POST['action']) && $_POST['action'] == 'get_page'){
    if(!empty($_POST['page'])) {
        $page = mysqli_real_escape_string($db, $_POST['page']);
        $query = mysqli_query($db, "SELECT id, body FROM pages WHERE title='{$page}';");
        $info = mysqli_fetch_assoc($query);
        if($info != null) {
            $result = $info;
        } else {
            $result = ['error' => 'Страница не найдена'];
        }
    }
}

/**
 * Сохранение новой информации профиля
 */
if(isset($_POST['action']) && $_POST['action'] == 'editprofile'){
    $token = mysqli_real_escape_string($db, strip_tags($_POST['token']));
    $about = mysqli_real_escape_string($db, strip_tags($_POST['about']));
    $avatarURL = mysqli_real_escape_string($db, strip_tags($_POST['avatarURL']));

    $editUser = mysqli_query($db, "UPDATE users SET about = '{$about}', avatar = '{$avatarURL}' WHERE token = '{$token}'");

    if (!$editUser) $result = ['error' => 'Ошибка сохранения данных на сервере.'];
}

echo json_encode($result);
?>