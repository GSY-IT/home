<?php
session_start();

// 定义文件路径
define('USERS_FILE', 'user.txt');
define('MESSAGES_FILE', 'msg.txt');
define('MAX_MESSAGES', 200); // 最多保存200条消息

// 初始化文件（如果不存在）
if (!file_exists(USERS_FILE)) {
    file_put_contents(USERS_FILE, '');
}
if (!file_exists(MESSAGES_FILE)) {
    file_put_contents(MESSAGES_FILE, '');
}

// 工具函数：安全输出
function safeEcho($text) {
    echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// 工具函数：安全存储（移除HTML标签后再存储）
function safeForFile($text) {
    // 先转义，再移除HTML标签，防止XSS
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    // 但我们要存储纯文本，所以再反转义一次
    return htmlspecialchars_decode($text);
}

// 工具函数：验证用户名
function validateUsername($username) {
    // 长度检查（1-5个字符）
    $len = mb_strlen($username, 'UTF-8');
    if ($len < 1 || $len > 5) {
        return false;
    }
    // 只允许中文、字母、数字、下划线
    return preg_match('/^[\x{4e00}-\x{9fa5}a-zA-Z0-9_]+$/u', $username);
}

// 工具函数：获取在线用户列表
function getOnlineUsers() {
    if (!file_exists(USERS_FILE)) {
        return [];
    }
    $content = file_get_contents(USERS_FILE);
    if (empty($content)) {
        return [];
    }
    // 按行分割，过滤空行
    $lines = explode("\n", trim($content));
    return array_filter($lines, function($line) {
        return !empty(trim($line));
    });
}

// 工具函数：更新用户活跃时间
function updateUserActivity($username) {
    $users = getOnlineUsers();
    // 由于我们只存储用户名，不需要更新时间
    // 这个函数保留以便将来扩展
    return true;
}

// 工具函数：移除用户
function removeUser($username) {
    $users = getOnlineUsers();
    $new_users = array_filter($users, function($user) use ($username) {
        return $user !== $username;
    });
    file_put_contents(USERS_FILE, implode("\n", $new_users));
}

// 工具函数：获取消息列表
function getMessages() {
    if (!file_exists(MESSAGES_FILE)) {
        return [];
    }
    $content = file_get_contents(MESSAGES_FILE);
    if (empty($content)) {
        return [];
    }
    // 按行分割，过滤空行
    $lines = explode("\n", trim($content));
    return array_filter($lines);
}

// 工具函数：添加消息
function addMessage($username, $message) {
    $messages = getMessages();
    
    // 格式化消息：时间|用户名|消息内容
    $time = date('Y-m-d H:i:s');
    $formatted = $time . '|' . $username . '|' . $message;
    
    // 添加到数组开头（最新的在前面）
    array_unshift($messages, $formatted);
    
    // 限制消息数量
    if (count($messages) > MAX_MESSAGES) {
        $messages = array_slice($messages, 0, MAX_MESSAGES);
    }
    
    // 保存到文件
    file_put_contents(MESSAGES_FILE, implode("\n", $messages));
}

// 处理登录
if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    
    // 验证用户名
    if (!validateUsername($username)) {
        $error = '用户名必须是1-5个字符，只能包含中文、字母、数字和下划线';
    } else {
        // 检查是否已存在
        $users = getOnlineUsers();
        if (in_array($username, $users)) {
            $error = '用户名已存在，请换一个';
        } else {
            // 添加用户
            $users[] = $username;
            file_put_contents(USERS_FILE, implode("\n", $users));
            
            // 保存会话
            $_SESSION['username'] = $username;
            $_SESSION['login_time'] = time();
        }
    }
}

// 处理退出
if (isset($_POST['logout'])) {
    if (isset($_SESSION['username'])) {
        removeUser($_SESSION['username']);
    }
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// 处理发送消息（AJAX）
if (isset($_POST['send_message']) && isset($_SESSION['username'])) {
    $message = trim($_POST['message'] ?? '');
    if (!empty($message)) {
        // 安全检查：移除可能有害的内容
        $message = strip_tags($message);
        $message = substr($message, 0, 500); // 限制长度
        
        addMessage($_SESSION['username'], $message);
    }
    exit();
}

// 获取在线用户列表（AJAX）
if (isset($_GET['get_users'])) {
    $users = getOnlineUsers();
    // 过滤掉当前用户？不，显示所有在线用户
    header('Content-Type: application/json');
    echo json_encode($users);
    exit();
}

// 获取消息列表（AJAX）
if (isset($_GET['get_messages'])) {
    $messages = getMessages();
    
    // 解析消息格式
    $formatted = [];
    foreach ($messages as $msg) {
        $parts = explode('|', $msg, 3);
        if (count($parts) === 3) {
            $formatted[] = [
                'time' => $parts[0],
                'username' => $parts[1],
                'message' => $parts[2]
            ];
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($formatted);
    exit();
}

// 检查登录状态
$is_logged_in = isset($_SESSION['username']);
$current_user = $is_logged_in ? $_SESSION['username'] : '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>一起聊 - 安全版</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Microsoft YaHei', sans-serif;
            padding: 20px;
        }
        
        /* 登录框 */
        .login-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
            text-align: center;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-container h1 {
            color: #333;
            margin-bottom: 30px;
            font-size: 2.5em;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .login-input {
            width: 100%;
            padding: 15px;
            font-size: 18px;
            border: 2px solid #e0e0e0;
            border-radius: 50px;
            margin-bottom: 20px;
            transition: all 0.3s;
            outline: none;
            text-align: center;
        }
        
        .login-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .login-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 15px 40px;
            font-size: 18px;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            font-weight: bold;
            letter-spacing: 1px;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .login-btn:active {
            transform: translateY(0);
        }
        
        .error-message {
            color: #ff4757;
            margin-top: 15px;
            font-size: 14px;
            padding: 10px;
            background: #ffeaea;
            border-radius: 10px;
            display: <?php echo isset($error) ? 'block' : 'none'; ?>;
        }
        
        /* 聊天室 */
        .chat-container {
            width: 100%;
            max-width: 1200px;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            animation: slideUp 0.5s ease;
        }
        
        .chat-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .chat-header h2 {
            font-size: 1.5em;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .current-user {
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 50px;
            font-weight: bold;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .chat-main {
            display: flex;
            height: 500px;
        }
        
        /* 消息区域 */
        .messages-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f5f7fb;
        }
        
        .messages-list {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column-reverse; /* 最新的在底部 */
        }
        
        .message-item {
            margin-bottom: 15px;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message-header {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
            font-size: 12px;
        }
        
        .message-username {
            font-weight: bold;
            color: #667eea;
            margin-right: 10px;
        }
        
        .message-time {
            color: #999;
            font-size: 11px;
        }
        
        .message-content {
            background: white;
            padding: 10px 15px;
            border-radius: 18px;
            display: inline-block;
            max-width: 80%;
            word-wrap: break-word;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            font-size: 14px;
            line-height: 1.5;
        }
        
        .own-message .message-content {
            background: #667eea;
            color: white;
        }
        
        .own-message .message-username {
            color: #764ba2;
        }
        
        .message-input-area {
            padding: 20px;
            background: white;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 10px;
        }
        
        .message-input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 50px;
            outline: none;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .message-input:focus {
            border-color: #667eea;
        }
        
        .send-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: bold;
        }
        
        .send-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .send-btn:active {
            transform: translateY(0);
        }
        
        /* 用户列表区域 */
        .users-area {
            width: 200px;
            background: white;
            border-left: 1px solid #e0e0e0;
            padding: 20px;
        }
        
        .users-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .users-list {
            list-style: none;
        }
        
        .user-item {
            padding: 8px 12px;
            margin-bottom: 5px;
            background: #f5f7fb;
            border-radius: 50px;
            font-size: 14px;
            color: #333;
            display: flex;
            align-items: center;
        }
        
        .user-item::before {
            content: '●';
            color: #2ecc71;
            margin-right: 8px;
            font-size: 10px;
        }
        
        .current-user-item {
            background: #667eea;
            color: white;
        }
        
        .current-user-item::before {
            color: white;
        }
        
        /* 新消息提示 */
        .new-message-indicator {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: #ff4757;
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(255, 71, 87, 0.4);
            display: none;
            animation: bounce 1s infinite;
            z-index: 1000;
            font-weight: bold;
        }
        
        @keyframes bounce {
            0%, 100% {
                transform: translateX(-50%) translateY(0);
            }
            50% {
                transform: translateX(-50%) translateY(-10px);
            }
        }
        
        /* 加载动画 */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .chat-main {
                flex-direction: column;
                height: auto;
            }
            
            .users-area {
                width: 100%;
                border-left: none;
                border-top: 1px solid #e0e0e0;
            }
            
            .message-input-area {
                flex-direction: column;
            }
            
            .send-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php if (!$is_logged_in): ?>
    <!-- 登录界面 -->
    <div class="login-container">
        <h1>一起聊</h1>
        <form method="POST" id="loginForm">
            <input 
                type="text" 
                name="username" 
                class="login-input" 
                placeholder="输入你的名字（1-5个字）"
                maxlength="5"
                required
                pattern="[\u4e00-\u9fa5a-zA-Z0-9_]{1,5}"
                title="只能包含中文、字母、数字和下划线，1-5个字"
            >
            <button type="submit" name="login" class="login-btn">进入聊天室</button>
            <div class="error-message" id="errorMsg">
                <?php echo isset($error) ? safeEcho($error) : ''; ?>
            </div>
        </form>
    </div>
    
    <script>
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const username = document.querySelector('input[name="username"]').value;
        if (username.length > 5) {
            e.preventDefault();
            document.getElementById('errorMsg').textContent = '用户名不能超过5个字';
            document.getElementById('errorMsg').style.display = 'block';
        }
    });
    </script>
    
    <?php else: ?>
    <!-- 聊天界面 -->
    <div class="chat-container">
        <div class="chat-header">
            <h2>💬 一起聊</h2>
            <div class="user-info">
                <span class="current-user">👤 <?php echo safeEcho($current_user); ?></span>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="logout" class="logout-btn">退出登录</button>
                </form>
            </div>
        </div>
        
        <div class="chat-main">
            <!-- 消息区域 -->
            <div class="messages-area">
                <div class="messages-list" id="messagesList"></div>
                
                <div class="message-input-area">
                    <input 
                        type="text" 
                        id="messageInput" 
                        class="message-input" 
                        placeholder="输入消息... (按Enter发送)"
                        maxlength="500"
                    >
                    <button class="send-btn" id="sendBtn">发送</button>
                </div>
            </div>
            
            <!-- 在线用户列表 -->
            <div class="users-area">
                <div class="users-title">在线用户 <span id="userCount">0</span></div>
                <div class="users-list" id="usersList"></div>
            </div>
        </div>
    </div>
    
    <!-- 新消息提示 -->
    <div class="new-message-indicator" id="newMessageIndicator" onclick="scrollToBottom()">
        📨 有新消息，点击查看
    </div>
    
    <script>
    // DOM 元素
    const messagesList = document.getElementById('messagesList');
    const usersList = document.getElementById('usersList');
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const newMessageIndicator = document.getElementById('newMessageIndicator');
    const userCount = document.getElementById('userCount');
    
    // 状态变量
    let lastMessageCount = 0;
    let isAtBottom = true;
    let currentUsername = '<?php echo safeEcho($current_user); ?>';
    
    // 检查是否在底部
    function checkIfAtBottom() {
        const scrollTop = messagesList.scrollTop;
        const scrollHeight = messagesList.scrollHeight;
        const clientHeight = messagesList.clientHeight;
        
        // 如果在底部20px范围内，认为是底部
        isAtBottom = scrollHeight - scrollTop - clientHeight < 20;
        
        if (isAtBottom) {
            newMessageIndicator.style.display = 'none';
        }
    }
    
    // 滚动到底部
    function scrollToBottom() {
        messagesList.scrollTop = messagesList.scrollHeight;
        newMessageIndicator.style.display = 'none';
        isAtBottom = true;
    }
    
    // 监听滚动
    messagesList.addEventListener('scroll', checkIfAtBottom);
    
    // 发送消息
    async function sendMessage() {
        const message = messageInput.value.trim();
        if (!message) return;
        
        // 安全检查
        if (message.length > 500) {
            alert('消息不能超过500个字符');
            return;
        }
        
        const formData = new FormData();
        formData.append('send_message', '1');
        formData.append('message', message);
        
        try {
            await fetch('1.php', {
                method: 'POST',
                body: formData
            });
            
            messageInput.value = '';
            loadMessages(); // 立即加载新消息
        } catch (error) {
            console.error('发送失败:', error);
        }
    }
    
    // 按Enter发送
    messageInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            sendMessage();
        }
    });
    
    sendBtn.addEventListener('click', sendMessage);
    
    // 加载消息
    async function loadMessages() {
        try {
            const response = await fetch('index.php?get_messages=1&t=' + Date.now());
            const messages = await response.json();
            
            // 检查是否有新消息
            if (messages.length > lastMessageCount && !isAtBottom) {
                newMessageIndicator.style.display = 'block';
            }
            
            // 渲染消息
            let html = '';
            messages.forEach(msg => {
                const isOwn = msg.username === currentUsername;
                html += `
                    <div class="message-item ${isOwn ? 'own-message' : ''}">
                        <div class="message-header">
                            <span class="message-username">${escapeHtml(msg.username)}</span>
                            <span class="message-time">${escapeHtml(msg.time)}</span>
                        </div>
                        <div class="message-content">${escapeHtml(msg.message)}</div>
                    </div>
                `;
            });
            
            messagesList.innerHTML = html;
            
            // 如果在底部，自动滚动
            if (isAtBottom) {
                scrollToBottom();
            }
            
            lastMessageCount = messages.length;
        } catch (error) {
            console.error('加载消息失败:', error);
        }
    }
    
    // 加载在线用户
    async function loadUsers() {
        try {
            const response = await fetch('index.php?get_users=1&t=' + Date.now());
            const users = await response.json();
            
            let html = '';
            users.forEach(user => {
                const isCurrent = user === currentUsername;
                html += `
                    <div class="user-item ${isCurrent ? 'current-user-item' : ''}">
                        ${escapeHtml(user)}
                    </div>
                `;
            });
            
            usersList.innerHTML = html;
            userCount.textContent = users.length;
        } catch (error) {
            console.error('加载用户列表失败:', error);
        }
    }
    
    // HTML转义函数
    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    
    // 定期更新
    loadMessages();
    loadUsers();
    
    // 每2秒更新消息，每5秒更新用户列表
    setInterval(loadMessages, 2000);
    setInterval(loadUsers, 5000);
    
    // 页面卸载前清理
    window.addEventListener('beforeunload', function() {
        // 可以在这里添加心跳或离开通知
    });
    </script>
    <?php endif; ?>
</body>
</html>