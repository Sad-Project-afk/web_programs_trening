document.addEventListener('DOMContentLoaded', () => {
  const authForm = document.getElementById('authForm');
  const toggleButtons = document.querySelectorAll('.toggle-btn');
  const formAction = document.getElementById('formAction');
  const submitBtn = document.getElementById('submitBtn');
  const nameField = document.getElementById('nameField');
  const confirmField = document.getElementById('confirmField');
  const nameInput = document.getElementById('name');
  const emailInput = document.getElementById('email');
  const passwordInput = document.getElementById('password');
  const confirmPasswordInput = document.getElementById('confirmPassword');
  const messageBox = document.getElementById('message');

  let mode = 'login';

  const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  const hasUpper = (value) => /[A-ZА-ЯЁ]/.test(value);
  const hasDigit = (value) => /\d/.test(value);
  const meetsPolicy = (value) => value.length >= 6 && hasUpper(value) && hasDigit(value);

  const clearStyle = (input) => {
    if (input) input.classList.remove('is-valid', 'is-invalid');
  };

  const paint = (input, ok) => {
    if (!input) return;
    clearStyle(input);
    if (input.value !== '') {
      input.classList.add(ok ? 'is-valid' : 'is-invalid');
    }
  };

  const validateField = (input, ok) => {
    if (!input) return;
    if (input.value === '') {
      clearStyle(input);
      return;
    }
    paint(input, ok);
  };

  const showMessage = (text, type) => {
    if (!messageBox) return;
    messageBox.textContent = text;
    messageBox.className = `message ${type || 'error'}`;
  };

  const renderMode = () => {
    const isRegister = mode === 'register';

    nameField?.classList.toggle('hidden', !isRegister);
    confirmField?.classList.toggle('hidden', !isRegister);
    submitBtn.textContent = isRegister ? 'Создать аккаунт' : 'Войти';
    formAction.value = mode;

    if (isRegister) {
      nameInput?.setAttribute('required', 'required');
      confirmPasswordInput?.setAttribute('required', 'required');
    } else {
      nameInput?.removeAttribute('required');
      confirmPasswordInput?.removeAttribute('required');
    }

    toggleButtons.forEach((button) => {
      button.classList.toggle('active', button.dataset.mode === mode);
    });

    refreshValidation();
  };

  const refreshValidation = () => {
    validateField(emailInput, EMAIL_RE.test(emailInput.value.trim()));

    if (mode === 'register') {
      validateField(nameInput, nameInput.value.trim() !== '');
      validateField(passwordInput, meetsPolicy(passwordInput.value));
      validateField(confirmPasswordInput, confirmPasswordInput.value === passwordInput.value);
    } else {
      clearStyle(passwordInput);
      clearStyle(confirmPasswordInput);
    }
  };

  emailInput?.addEventListener('input', () => {
    validateField(emailInput, EMAIL_RE.test(emailInput.value.trim()));
  });

  nameInput?.addEventListener('input', () => {
    validateField(nameInput, nameInput.value.trim() !== '');
  });

  passwordInput?.addEventListener('input', () => {
    validateField(passwordInput, mode === 'register' ? meetsPolicy(passwordInput.value) : true);
    validateField(confirmPasswordInput, confirmPasswordInput.value === passwordInput.value);
  });

  confirmPasswordInput?.addEventListener('input', () => {
    validateField(confirmPasswordInput, confirmPasswordInput.value === passwordInput.value);
  });

  toggleButtons.forEach((button) => {
    button.addEventListener('click', () => {
      mode = button.dataset.mode;
      showMessage('', '');
      renderMode();
    });
  });

  authForm?.addEventListener('submit', (event) => {
    const isRegister = mode === 'register';

    if (isRegister) {
      const emptyName = nameInput.value.trim() === '';
      const invalidEmail = !EMAIL_RE.test(emailInput.value.trim());
      const invalidPolicy = !meetsPolicy(passwordInput.value);
      const mismatch = confirmPasswordInput.value !== passwordInput.value;

      if (emptyName || invalidEmail) {
        event.preventDefault();
        showMessage('Заполните все поля корректно.', 'error');
        return;
      }

      if (invalidPolicy) {
        event.preventDefault();
        showMessage('Пароль должен быть не короче 6 символов и содержать заглавную букву и цифру.', 'error');
        return;
      }

      if (mismatch) {
        event.preventDefault();
        showMessage('Пароли не совпадают.', 'error');
        return;
      }
    } else {
      if (emailInput.value.trim() === '' || passwordInput.value === '') {
        event.preventDefault();
        showMessage('Введите email и пароль.', 'error');
        return;
      }
    }

    // Поля очищаются после отправки формы.
    nameInput.value = '';
    emailInput.value = '';
    passwordInput.value = '';
    confirmPasswordInput.value = '';
    clearStyle(nameInput);
    clearStyle(emailInput);
    clearStyle(passwordInput);
    clearStyle(confirmPasswordInput);
  });

  // Возврат браузером из bfcache: форма не должна показывать прежние данные.
  window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
      nameInput.value = '';
      emailInput.value = '';
      passwordInput.value = '';
      confirmPasswordInput.value = '';
      clearStyle(nameInput);
      clearStyle(emailInput);
      clearStyle(passwordInput);
      clearStyle(confirmPasswordInput);
    }
  });

  renderMode();
});