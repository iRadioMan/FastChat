<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="assets/css/style.css">
    <title>FastChat - Быстрый и минималистичный чат</title>
    <script src="assets/js/jquery-3.4.1.min.js" defer></script>
    <script src="assets/js/main.js" defer></script>
    <script src='assets/js/inputEmoji.js' defer></script>
</head>
<body>

<div id="modal-background"></div>
<div id="modal-content"></div>

    <div id="unauthorized">
        <div class="navLinks">
            <a onclick='$("#block_auth").hide(); $("#block_register").show();' href="#">Регистрация</a>
            <a onclick='$("#block_register").hide(); $("#block_auth").show();' href="#">Авторизация</a>
        </div>

        <div style="display: none;" class="middleBox" id="block_register">
            <p class="pageTitle">Регистрация пользователя</p>
            <form class="authForm">
                <p class="authText">Логин:</p>
                <input id="register_login" type="text" name="login">
                <p class="authText">Пароль:</p>
                <input id="register_pass" type="password" name="pass">
                <p class="authText">О себе:</p>
                <input id="register_about" type="text" name="about">
                <input id="register_button" type="button" name="do_signup" value="Зарегистрироваться">
            </form>
        </div>

        <div class="middleBox" id="block_auth">
            <p class="pageTitle">Авторизация</p>
            <form class="authForm">
                <p class="authText">Логин:</p>
                <input id="authorize_login" type="text" name="login">
                <p class="authText">Пароль:</p>
                <input id="authorize_pass" type="password" name="pass">
                <input id="login_button" type="button" name="do_login" value="Войти">
            </form>
        </div>
    </div>

    <div id="authorized">

        <div class="btnContainer">
            <p id="authText" class="authText"></p>
            <input type="button" class='controlButton' id="profile_button" name="profile_button" value="Профиль">
            <input type="button" class='controlButton' id="help_button" name="help_button" value="Справка">
            <input type="button" class='controlButton' id="log_button" name="log_button" value="Лог изменений">
            <input type="button" class='controlButton' id="logout_button" name="logout" value="Выйти">
        </div>

        <div class="middleBox">
            <div class="chatBox" id="chatBox"></div>
            <div class="usersOnlineBox" id="usersOnlineBox"></div>

            <form id="enterMsgForm">
                <a id="enterMsgText">Ваше сообщение: </a>
                <input type="text" id="msgtext" name="message">
                <input type="button" class='controlButton' id="sendmsg_button" name="send_message" value="Отправить">
            </form>
        </div>

    </div>
</body>
</html>