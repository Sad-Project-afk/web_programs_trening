document.addEventListener('DOMContentLoaded', () => {
  const authForm = document.getElementById('authForm');
  const toggleButtons = document.querySelectorAll('.toggle-btn');
  const formAction = document.getElementById('formAction');
  const submitBtn = document.getElementById('submitBtn');
  const nameField = document.getElementById('nameField');
  const confirmField = document.getElementById('confirmField');
  const confirmPasswordInput = document.getElementById('confirmPassword');
  const nameInput = document.getElementById('name');
  const passwordInput = document.getElementById('password');
  const messageBox = document.getElementById('message');

  let mode = 'login';

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
  };

  toggleButtons.forEach((button) => {
    button.addEventListener('click', () => {
      mode = button.dataset.mode;
      if (messageBox) {
        messageBox.textContent = '';
      }
      renderMode();
    });
  });

  authForm?.addEventListener('submit', (event) => {
    const isRegister = mode === 'register';

    if (isRegister && confirmPasswordInput.value !== passwordInput.value) {
      event.preventDefault();
      if (messageBox) {
        messageBox.textContent = 'Пароли не совпадают.';
        messageBox.className = 'message error';
      }
    }
  });

  renderMode();
});
