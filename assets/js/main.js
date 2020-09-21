$("#login_button").click(tryLogin);
$("#register_button").click(tryRegister);

$("#sendmsg_button").click(trySendMessage);

$("#profile_button").click(editProfileInfo);
$("#help_button").click(() => showPage("help"));
$("#log_button").click(() => showPage("log"));
$("#logout_button").click(Logout);

$("#unauthorized").show();
$("#authorized").hide();

$("#modal-launcher, #modal-background, #modal-close").click(function() {
    $("#modal-content, #modal-background").toggleClass("active");
});

$("form").submit(function(e){
    trySendMessage();
    e.preventDefault(e);
});

let userInfo = {};
let lastMsgId = 0;
let isSmilesLoaded = false;
let isUpdatingMessages = false;
let banSound = new Audio("/resources/audio/valakas-ban.mp3");
let updInterval;

tryAutoLogin();

/**
 * Попытка авторизации
 * @returns {Promise<void>}
 */
async function tryLogin(){
    let login = $("#authorize_login").val();
    let pass = $("#authorize_pass").val();
    let res = await $.post("/api.php", {
        action: "authorize",
        login: login,
        pass: pass,
    });
    if(res.token != undefined){
        localStorage.token = res.token;
        tryAutoLogin();
    } else {
        alert("Ошибка! Неверный логин или пароль.");
    }
}

/**
 * Попытка регистрации
 * @returns {Promise<void>}
 */
async function tryRegister(){
    let login = $("#register_login").val().replace(/\s/g, '');
    if (login.length > 20) {
        alert('Ошибка: слишком длинный логин (максимальная длина 20 символов)');
        return;
    }

    let pass = $("#register_pass").val();
    let about = $("#register_about").val();
    let res = await $.post("/api.php", {
        action: "register",
        login: login,
        pass: pass,
        about: about,
    });

    if (res['error'] != null) {
        alert(res['error']);
    } else {
        alert("Вы успешно зарегистрировались.");
        $("#authorize_login").val(login);
    }

    $("#block_register").hide();
    $("#block_auth").show();
}

/**
 * Попытаться автоматически войти в аккаунт
 * @returns {Promise<void>}
 */
async function tryAutoLogin() {
    if(localStorage.token != undefined) {
        let res = await $.post('/api.php', {
            action: "get_user_data",
            token: localStorage.token
        });

        if (res['error'] != undefined && res['error'].startsWith("ban")) {
            Logout();
            showBanReason(res['error'].substr(4));
            return;
        }

        if (res.id != undefined) {
            userInfo = res;
            showMainScreen();
        }
    }
}

/**
 * Обновление чата (получение сообщений с id >= getFrom)
 */
async function updateChat(getFrom) {
    if (isUpdatingMessages) return;

    isUpdatingMessages = true;

    if(localStorage.token != undefined) {

        //получаем сообщения
        let res = await $.post('/api.php', {
            action: "getmessages",
            token: localStorage.token,
            id: userInfo['id'],
            getFrom: getFrom
        });

        if (res.length != 0) {
            if (res['error'] != undefined) {
                if (res['error'].startsWith("ban")) {
                    isUpdatingMessages = false;
                    await Logout();
                    showBanReason(res['error'].substr(4));
                    return;
                }
            }

            res.forEach(element => {
                addChatMessage(element);
            });
            scrollChat();
            lastMsgId = res[res.length - 1]['id'];
        }

        //получаем пользователей онлайн
        res = await $.post('/api.php', {
            action: "getusersonline",
            token: localStorage.token
        });

        addUsersOnline(res)
    }

    isUpdatingMessages = false;
}

/**
 * Полное обновление чата (получение всех сообщений)
 */
async function refreshChat() {
    clearChat();
    await updateChat(0);
}

/**
 * Попытка отправки сообщения в чат
 */
async function trySendMessage() {
    if ($("#msgtext").val() == '' && $("#msgtext").val() == ' ') return;

    if (userInfo != null) {
        let senderID = userInfo['id'];
        let message = $("#msgtext").val();
        let res;

        if (message.startsWith("/ban ")) {
            let userAndReason = message.substr(5);
            let userToBan = userAndReason.substr(0, userAndReason.indexOf(' '));
            let banReason = userAndReason.substr(userAndReason.indexOf(' ') + 1);

            res = await $.post("/api.php", {
                action: "banuser",
                banReason: banReason,
                token: localStorage.token,
                userToBan: userToBan
            });
            if (res.length != 0) {
                if (res['error'] != undefined)
                    alert(res['error']);
                else
                    alert('Пользователь заблокирован!');
            }
        } else if (message.startsWith("/unban")) {
            let user = message.substr(7);

            res = await $.post("/api.php", {
                action: "unbanuser",
                token: localStorage.token,
                userToUnban: user
            });
            if (res.length != 0) {
                if (res['error'] != undefined)
                    alert(res['error']);
                else
                    alert('Пользователь разблокирован!');
            }
        } else if (message.startsWith("http") &&
        (message.endsWith(".jpg")
            || message.endsWith(".png")
            || message.endsWith(".jpeg")
            || message.endsWith(".gif"))) {
                res = await $.post("/api.php", {
                    action: "sendimage",
                    token: localStorage.token,
                    senderID: senderID,
                    imageURL: message,
                });
        } else {
            res = await $.post("/api.php", {
                action: "sendmsg",
                token: localStorage.token,
                senderID: senderID,
                message: message,
            });
        }

        if (res.length != 0) {
            if (res['error'] != undefined) {
                alert(res['error']);
            }
        }
        $("#msgtext").val('');
        await updateChat(Number(lastMsgId) + Number(1));
    } else {
        alert("Ошибка отправки сообщения. Пожалуйста, авторизуйтесь в системе заново!");
        await Logout();
    }
}

/**
 * Деавторизация пользователя
 * @returns {Promise<void>}
 */
async function Logout() {
    clearInterval(updInterval);

    let res = await $.post('/api.php', {
        action: "logout",
        token: localStorage.token
    });

    delete localStorage.token;
    userInfo = {};
    hideMainScreen();
}

/**
 * Послать запрос на сервер, что мы зашли в чат
 */
async function onlineHandshake() {
    let login = userInfo['login'];
    let res = await $.post('/api.php', {
        action: "handshake",
        token: localStorage.token,
        login: login
    });
}

/**
 * Загрузка смайликов
 */
function loadSmiles() {
    isSmilesLoaded = true;
    $('#msgtext').emoji({place: 'after'});
}

/**
 * Открытие основного окна (чат)
 */
function showMainScreen(){
    refreshChat();

    if (!isSmilesLoaded)
        loadSmiles();

    onlineHandshake();

    $("#unauthorized").hide();
    $("#authorized").show();
    $("#authText").text("Вы вошли как " + userInfo.login);

    addSystemMessage("Добро пожаловать в SimpleChat!", true);

    updInterval = setInterval(() => updateChat(Number(lastMsgId) + Number(1)), 2000);
}

/**
 * Скрытие основного окна
 */
function hideMainScreen() {
    $("#authorized").hide();
    $("#unauthorized").show();
}

/**
 * Показ инфы пользователя
 * @param userLogin
 * @returns {Promise<void>}
 */
async function showUserInfo(userLogin) {
    $("#modal-content").empty();

    let res = await $.post('/api.php', {
        action: "get_user_info",
        token: localStorage.token,
        login: userLogin
    });

    if (res.id != undefined) {
        //$("#modal-content").append("<button id='modal-close'>Закрыть</button>")
        $("#modal-content").append("<div class='modalTitle'>Информация о пользователе " + userLogin + ": </div>");
        $("#modal-content").append("<div class='infoBlock'>ID пользователя: " + res.id + "</div>");
        $("#modal-content").append("<div class='infoBlock'>Дата регистрации: " + res['dataReg'] + "</div>");
        $("#modal-content").append("<div class='infoBlock'>О себе: " + res['about'] + "</div>");
        if (res['avatar'] != null) {
            $("#modal-content").append("<div class='userAvatar' style='background: url(&#34;" + res['avatar'] + "&#34;) no-repeat; background-size: cover;'></div>");
        }
        $("#modal-content, #modal-background").toggleClass("active");
    } else {
        alert('Ошибка на сервере при получении информации о пользователе!');
    }
}

/**
 * Редактирование информации о себе
 * @returns {Promise<void>}
 */
async function editProfileInfo() {
    $("#modal-content").empty();

    let res = await $.post('/api.php', {
        action: "get_user_info",
        token: localStorage.token,
        login: userInfo['login']
    });

    if (res.id != undefined) {
        $("#modal-content").append("<div class='modalTitle'>Информация о пользователе " + userInfo['login'] + ": </div>");
        $("#modal-content").append("<div class='infoBlock'>ID пользователя: " + res.id + "</div>");
        $("#modal-content").append("<div class='infoBlock'>Дата регистрации: " + res['dataReg'] + "</div>");
        $("#modal-content").append("<form id='editProfileForm'><div class='infoBlock'>О себе: <input type='text' id='editProfileAbout' value='" + res['about'] + "'></div>");
        $("#modal-content").append("<div class='userAvatar' style='background: url(&#34;" + res['avatar'] + "&#34;) no-repeat; background-size: cover;'><input type='text' id='editProfileAvatarURL' value='" + res['avatar'] + "'></div>");

        $("#modal-content").append("<input type='button' id='editProfileSaveBtn' onclick='saveProfileInfo();' value='Сохранить изменения'></form>");

        $("#modal-content, #modal-background").toggleClass("active");
    } else {
        alert('Ошибка на сервере при получении информации о пользователе!');
    }
}

/**
 * Сохранение информации профиля
 * @returns {Promise<void>}
 */
async function saveProfileInfo() {
    let about = $("#editProfileAbout").val();
    let avatarURL = $("#editProfileAvatarURL").val();

    let res = await $.post("/api.php", {
        action: "editprofile",
        token: localStorage.token,
        about: about,
        avatarURL: avatarURL
    });

    if (res['error'] != null) {
        alert(res['error']);
    } else {
        alert("Изменения сохранены!");
    }
}

/**
 * Показ справки
 * @param element
 */
async function showPage(page) {
    $("#modal-content").empty();

    let res = await $.post('/api.php', {
        action: "get_page",
        token: localStorage.token,
        page: page
    });

    if (res.id != undefined) {
        $("#modal-content").append(res['body']);
        $("#modal-content, #modal-background").toggleClass("active");
    } else {
        alert('Ошибка на сервере при получении страницы!');
    }
}

/*
    Показ странички с причиной бана
 */
function showBanReason(reason) {
    $("#modal-content").empty();

    $("#modal-content").append("<div class='modalTitle'>Вам прилетел БАН</div>");
    $("#modal-content").append("<img src='/assets/img/ban.jpg' style='display: block; margin: auto auto 30px;'>");
    $("#modal-content").append("<div class='infoBanReason'><b>Причина: </b>" + reason + "</div>");

    $("#modal-content, #modal-background").toggleClass("active");
    banSound.play();
}

/*
    Удаление сообщения (админ)
 */
async function deleteMessage(id) {
    let res = await $.post('/api.php', {
        action: "deletemessage",
        token: localStorage.token,
        id: id,
    });

    await refreshChat();
}

/*
Функции для чата
 */

function addChatMessage(element) {
    let senderLogin = element['senderLogin'];
    let message = element['message'];
    let msgToAppend = "<div class='";

    if (senderLogin == "system") {
        //все системные сообщения
        if (message.startsWith("handshake")) {
            let user = message.substr(10);
            msgToAppend += "message'> [" + element['dateTime'] + "] "
                + "<a href='#' onclick='$(&#34;#msgtext&#34;).val(&#34;@" + user + " &#34;); $(&#34;#msgtext&#34;).focus();'>" + user + "</a><img src='/assets/img/info.png' width='16' height='16' onclick='showUserInfo(" + "&#34;" + user + "&#34;);'> вошел в чат";
        } else {
            msgToAppend += "systemMessage'> [" + element['dateTime'] + "] <b>Система: " + message + "</b>";
        }
    } else if (message.startsWith("@" + userInfo.login)) {
        msgToAppend += "pmessage'><div class='chatPreMessage'> [" + element['dateTime']  + "] <img class='chatAvatar' src='" + element['avatar'] + "'> "
        + "<a href='#' onclick='$(&#34;#msgtext&#34;).val(&#34;@" + senderLogin + " &#34;); $(&#34;#msgtext&#34;).focus();'>" + senderLogin + "</a><img src='/assets/img/info.png' width='16' height='16' onclick='showUserInfo("+ "&#34;" + senderLogin + "&#34;);'><a class='systemMessage'> (вам)</a>:</div> "
            + message.substr(message.indexOf(' ') + 1);
    } else if (message.startsWith("/me")) {
        msgToAppend += "message'><div class='chatPreMessage'> [" + element['dateTime']  + "] "
            + "<a href='#' onclick='$(&#34;#msgtext&#34;).val(&#34;@" + senderLogin + " &#34;); $(&#34;#msgtext&#34;).focus();'>" + senderLogin + "</a><img src='/assets/img/info.png' width='16' height='16' onclick='showUserInfo("+ "&#34;" + senderLogin + "&#34;);'> "
            + "<a style='font-style: italic;'>" + message.substr(4) + "</a>";
    } else {
        msgToAppend += "message'><div class='chatPreMessage'> [" + element['dateTime']  + "] <img class='chatAvatar' src='" + element['avatar'] + "'> "
        + "<a href='#' onclick='$(&#34;#msgtext&#34;).val(&#34;@" + senderLogin + " &#34;); $(&#34;#msgtext&#34;).focus();'>" + senderLogin + "</a><img src='/assets/img/info.png' width='16' height='16' onclick='showUserInfo("+ "&#34;" + senderLogin + "&#34;);'>:</div> "
        + message;
    }

    /* функции админа (строго по токену!)*/
    if (userInfo['isAdmin'] == 1) {
        //удаление сообщения
        msgToAppend += "<img src='/assets/img/delete.png' width='16' height='16' style='margin-left: 5px; margin-bottom: -3px;' onclick='deleteMessage(" + element['id'] + ");'>";
    }

    msgToAppend += "</div>";

    $("#chatBox").append(msgToAppend);
}

function addUsersOnline(res) {
    $("#usersOnlineBox").empty();
    $("#usersOnlineBox").append("Пользователи онлайн: ");

    if (res.length != 0) {
        res.forEach(element => {
            if (element['isAdmin'] == "1") $("#usersOnlineBox").append("<a style='color: red;'>[admin] </a>");
            $("#usersOnlineBox").append("<a href='#' onclick='$(&#34;#msgtext&#34;).val(&#34;@" + element['login'] + " &#34;); $(&#34;#msgtext&#34;).focus();'>" + element['login'] + "</a><img src='/assets/img/info.png' width='16' height='16' onclick='showUserInfo(" + "&#34;" + element['login'] + "&#34;);'>  ");
        });
    }
}

function addSystemMessage(text, breakline) {
    $("#chatBox").append("<div class='systemMessage'>" + text + "</div>");

    if (breakline)
        $("#chatBox").append("<div class='systemMessage'>&nbsp;</div>");
}

function scrollChat() {
    $("#chatBox").scrollTop($("#chatBox")[0].scrollHeight - $("#chatBox").height());
}

function clearChat() {
    $("#chatBox").empty();
}