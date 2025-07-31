<?php
// Start session to check if user is already logged in
session_start();

// If user is already logged in, redirect them to index.php
if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login e Registro</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f4f4f4;
            margin: 0;
            flex-direction: column;
        }
        .container {
            background-color: #fff;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 350px;
            margin-bottom: 20px;
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        input[type="text"],
        input[type="password"],
        input[type="email"] {
            width: 100%; /* Changed for better box-sizing behavior */
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box; /* Added for consistency */
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color: #0056b3;
        }
        .message {
            margin-top: 15px;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        #loginMessage, #registerMessage {
            display: none; /* Esconde as mensagens inicialmente */
        }
        .hidden {
            display: none;
        }
        .tab-buttons {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
        }
        .tab-buttons button {
            flex-grow: 1;
            padding: 10px;
            background-color: #e0e0e0;
            color: #333;
            border: none;
            border-radius: 4px 4px 0 0;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .tab-buttons button.active {
            background-color: #007bff;
            color: white;
        }
        .tab-buttons button:not(.active):hover {
            background-color: #d0d0d0;
        }
        small {
            font-size: 0.8rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="tab-buttons">
            <button id="showLoginBtn" class="active">Login</button>
            <button id="showRegisterBtn">Registrar</button>
        </div>

        <div id="loginFormContainer">
            <h2>Login</h2>
            <form id="loginForm">
                <div class="form-group">
                    <label for="loginUsername">Usuário:</label>
                    <input type="text" id="loginUsername" name="username" required>
                </div>
                <div class="form-group">
                    <label for="loginPassword">Senha:</label>
                    <input type="password" id="loginPassword" name="password" required>
                </div>
                <button type="submit">Entrar</button>
                <div id="loginMessage" class="message"></div>
            </form>
        </div>

        <div id="registerFormContainer" class="hidden">
            <h2>Registro</h2>
            <form id="registerForm">
                <div class="form-group">
                    <label for="registerUsername">Usuário:</label>
                    <input type="text" id="registerUsername" name="username" required>
                </div>
                 <div class="form-group">
                    <label for="registerEmail">E-mail:</label>
                    <input type="email" id="registerEmail" name="email" required>
                </div>
                <div class="form-group">
                    <label for="registerKey">Chave Pix:</label>
                    <input type="text" id="registerKey" name="key" required>
                </div>
                <div class="form-group">
                    <label for="registerPassword">Senha:</label>
                    <input type="password" id="registerPassword" name="password" required>
                    <small>Mínimo 8 caracteres, uma maiúscula, um número, um símbolo.</small>
                </div>
                <button type="submit">Registrar</button>
                <div id="registerMessage" class="message"></div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // Form and message elements
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');
        const loginMessage = document.getElementById('loginMessage');
        const registerMessage = document.getElementById('registerMessage');

        // Tab elements
        const loginFormContainer = document.getElementById('loginFormContainer');
        const registerFormContainer = document.getElementById('registerFormContainer');
        const showLoginBtn = document.getElementById('showLoginBtn');
        const showRegisterBtn = document.getElementById('showRegisterBtn');

        // Function to switch between login and register tabs
        function showTab(tab) {
            if (tab === 'login') {
                loginFormContainer.classList.remove('hidden');
                registerFormContainer.classList.add('hidden');
                showLoginBtn.classList.add('active');
                showRegisterBtn.classList.remove('active');
            } else {
                loginFormContainer.classList.add('hidden');
                registerFormContainer.classList.remove('hidden');
                showLoginBtn.classList.remove('active');
                showRegisterBtn.classList.add('active');
            }
            loginMessage.style.display = 'none';
            registerMessage.style.display = 'none';
        }

        showLoginBtn.addEventListener('click', () => showTab('login'));
        showRegisterBtn.addEventListener('click', () => showTab('register'));

        // Function to display messages
        function showMessage(element, text, isSuccess) {
            element.textContent = text;
            element.className = isSuccess ? 'message success' : 'message error';
            element.style.display = 'block';
        }

        // Login form submission
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = {
                action: 'login',
                username: document.getElementById('loginUsername').value,
                password: document.getElementById('loginPassword').value
            };

            try {
                const response = await fetch('auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();

                if (result.success) {
                    window.location.href = result.redirect;
                } else {
                    showMessage(loginMessage, result.message, false);
                }
            } catch (error) {
                showMessage(loginMessage, 'Ocorreu um erro de conexão.', false);
            }
        });

        // Register form submission
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const password = document.getElementById('registerPassword').value;
            const passwordRegex = /^(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?~]).{8,}$/;

            if (!passwordRegex.test(password)) {
                showMessage(registerMessage, 'Senha inválida. Verifique os requisitos.', false);
                return;
            }

            const data = {
                action: 'register',
                username: document.getElementById('registerUsername').value,
                email: document.getElementById('registerEmail').value,
                key: document.getElementById('registerKey').value,
                password: password
            };

            try {
                const response = await fetch('auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                
                showMessage(registerMessage, result.message, result.success);
                if (result.success) {
                    registerForm.reset();
                }
            } catch (error) {
                showMessage(registerMessage, 'Ocorreu um erro de conexão.', false);
            }
        });
    });
    </script>
</body>
</html>