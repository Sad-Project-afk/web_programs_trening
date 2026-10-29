const authForm = document.getElementById('authForm');
const submitBtn = document.getElementById('submitBtn');
const messageBox = document.getElementById('message');
const modeButtons = document.querySelectorAll('.toggle-btn');
const nameField = document.getElementById('nameField');
const confirmField = document.getElementById('confirmField');

let mode = 'login';

const renderMode = () => {
  const isRegister = mode === 'register';

  nameField.classList.toggle('hidden', !isRegister);
  confirmField.classList.toggle('hidden', !isRegister);
  submitBtn.textContent = isRegister ? 'Создать аккаунт' : 'Войти';

  const nameInput = document.getElementById('name');
  if (nameInput && isRegister) {
    nameInput.setAttribute('required', 'required');
  } else if (nameInput) {
    nameInput.removeAttribute('required');
  }

  const confirmInput = document.getElementById('confirmPassword');
  if (confirmInput && isRegister) {
    confirmInput.setAttribute('required', 'required');
  } else if (confirmInput) {
    confirmInput.removeAttribute('required');
  }

  modeButtons.forEach((button) => {
    button.classList.toggle('active', button.dataset.mode === mode);
  });
};

const getUsers = () => JSON.parse(localStorage.getItem('fitlogUsers') || '[]');

const saveUser = (user) => {
  const users = getUsers();
  users.push(user);
  localStorage.setItem('fitlogUsers', JSON.stringify(users));
};

const findUser = (email, password) => {
  const users = getUsers();
  return users.find((user) => user.email === email && user.password === password);
};

const setMessage = (text, isError = false) => {
  messageBox.textContent = text;
  messageBox.style.color = isError ? '#d9485f' : '#0ea5a4';
};

modeButtons.forEach((button) => {
  button.addEventListener('click', () => {
    mode = button.dataset.mode;
    setMessage('');
    renderMode();
  });
});

authForm.addEventListener('submit', (event) => {
  event.preventDefault();

  const formData = new FormData(authForm);
  const name = (formData.get('name') || '').toString().trim();
  const email = (formData.get('email') || '').toString().trim();
  const password = (formData.get('password') || '').toString();
  const confirmPassword = (formData.get('confirmPassword') || '').toString();

  if (!email || !password) {
    setMessage('Пожалуйста, заполните все обязательные поля.', true);
    return;
  }

  if (mode === 'register') {
    if (!name) {
      setMessage('Введите имя для создания аккаунта.', true);
      return;
    }

    if (password !== confirmPassword) {
      setMessage('Пароли не совпадают.', true);
      return;
    }

    const existingUsers = getUsers();
    const isExists = existingUsers.some((user) => user.email === email);

    if (isExists) {
      setMessage('Пользователь с таким email уже существует.', true);
      return;
    }

    saveUser({ name, email, password });
    setMessage(`Аккаунт создан для ${name}. Теперь можно войти.`);
    mode = 'login';
    authForm.reset();
    renderMode();
    return;
  }

  const user = findUser(email, password);

  if (!user) {
    setMessage('Неверный email или пароль.', true);
    return;
  }

  setMessage(`Добро пожаловать, ${user.name}!`);
  console.log('User authorized:', user);
});

renderMode();
