document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const loginMessage = document.getElementById('loginMessage');
    const registerMessage = document.getElementById('registerMessage');

    const loginFormContainer = document.getElementById('loginFormContainer');
    const registerFormContainer = document.getElementById('registerFormContainer');
    const showLoginBtn = document.getElementById('showLoginBtn');
    const showRegisterBtn = document.getElementById('showRegisterBtn');

    // Função para mostrar ou esconder as abas
    function showTab(tab) {
        if (tab === 'login') {
            loginFormContainer.classList.remove('hidden');
            registerFormContainer.classList.add('hidden');
            showLoginBtn.classList.add('active');
            showRegisterBtn.classList.remove('active');
            // Limpar mensagens e campos ao trocar de aba
            loginMessage.style.display = 'none';
            registerMessage.style.display = 'none';
            registerForm.reset();
        } else {
            loginFormContainer.classList.add('hidden');
            registerFormContainer.classList.remove('hidden');
            showLoginBtn.classList.remove('active');
            showRegisterBtn.classList.add('active');
            // Limpar mensagens e campos ao trocar de aba
            loginMessage.style.display = 'none';
            registerMessage.style.display = 'none';
            loginForm.reset();
        }
    }

    // Event listeners para os botões de aba
    showLoginBtn.addEventListener('click', () => showTab('login'));
    showRegisterBtn.addEventListener('click', () => showTab('register'));

    // Inicialmente, mostrar a aba de login
    showTab('login');

    // Validação de senha no lado do cliente
    function validatePassword(password) {
        const minLength = 8;
        const hasUpperCase = /[A-Z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasSpecialChar = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?~]/.test(password);

        return password.length >= minLength && hasUpperCase && hasNumber && hasSpecialChar;
    }

    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const username = document.getElementById('registerUsername').value;
        const password = document.getElementById('registerPassword').value;
        const email = document.getElementById('registerEmail').value;
        const key = document.getElementById('registerKey').value;

        if (!validatePassword(password)) {
            registerMessage.textContent = 'A senha deve ter no mínimo 8 caracteres, uma letra maiúscula, um número e um símbolo especial.';
            registerMessage.className = 'message error';
            registerMessage.style.display = 'block';
            return;
        }

        const data = {
            action: 'register',
            username,
            password,
            email,
            key
        };

        try {
            const response = await fetch('auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                registerMessage.textContent = result.message;
                registerMessage.className = 'message success';
                registerForm.reset(); // Limpa o formulário após o sucesso
            } else {
                registerMessage.textContent = result.message;
                registerMessage.className = 'message error';
            }
            registerMessage.style.display = 'block';

        } catch (error) {
            console.error('Erro:', error);
            registerMessage.textContent = 'Ocorreu um erro ao registrar. Tente novamente.';
            registerMessage.className = 'message error';
            registerMessage.style.display = 'block';
        }
    });

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const username = document.getElementById('loginUsername').value;
        const password = document.getElementById('loginPassword').value;

        const data = {
            action: 'login',
            username,
            password
        };

        try {
            const response = await fetch('auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                loginMessage.textContent = result.message;
                loginMessage.className = 'message success';
                loginForm.reset(); // Limpa o formulário após o sucesso
                // Redirecionar ou exibir dashboard aqui se necessário
                alert('Login bem-sucedido! Bem-vindo(a), ' + username + '!'); // Exemplo simples
            } else {
                loginMessage.textContent = result.message;
                loginMessage.className = 'message error';
            }
            loginMessage.style.display = 'block';

        } catch (error) {
            console.error('Erro:', error);
            loginMessage.textContent = 'Ocorreu um erro ao logar. Tente novamente.';
            loginMessage.className = 'message error';
            loginMessage.style.display = 'block';
        }
    });
});