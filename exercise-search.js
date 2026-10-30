/**
 * Поиск упражнений (комбобокс), общий для страницы тренировки и прогресса.
 * Улучшает блок с <input> и <ul role="listbox">:
 *   <div class="ex-search">
 *     <input type="text" class="ex-search-input" role="combobox" aria-expanded="false" ... />
 *     <ul class="ex-search-list" role="listbox" hidden></ul>
 *   </div>
 */
(function () {
  let openSearch = null;

  const closeOpen = () => {
    if (openSearch) {
      openSearch.close();
      openSearch = null;
    }
  };

  document.addEventListener('mousedown', (event) => {
    if (event.target.closest && event.target.closest('.ex-search')) {
      return;
    }
    closeOpen();
  });

  // Пока список открыт, следим за прокруткой и поворотом экрана,
  // чтобы держать его пристыкованным к полю.
  const repositionOpen = () => {
    if (openSearch && openSearch.position) {
      openSearch.position();
    }
  };
  window.addEventListener('scroll', repositionOpen, true);
  window.addEventListener('resize', repositionOpen);

  function setupExerciseSearch(wrapper, options, config) {
    const input = wrapper.querySelector('input.ex-search-input');
    const list = wrapper.querySelector('.ex-search-list');

    if (!input || !list) {
      return null;
    }

    const onSelect = (config && config.onSelect) || null;

    const render = (query) => {
      const needle = (query || '').trim().toLowerCase();
      const matches = needle === ''
        ? options.slice()
        : options.filter((name) => name.toLowerCase().includes(needle));

      list.textContent = '';

      if (matches.length === 0) {
        const empty = document.createElement('li');
        empty.className = 'ex-search-empty';
        empty.textContent = 'Ничего не найдено';
        empty.setAttribute('role', 'option');
        empty.setAttribute('aria-disabled', 'true');
        list.appendChild(empty);
        return;
      }

      matches.forEach((name) => {
        const item = document.createElement('li');
        item.className = 'ex-search-option';
        item.setAttribute('role', 'option');
        item.textContent = name;

        item.addEventListener('mousedown', (event) => event.preventDefault());
        item.addEventListener('click', () => {
          input.value = name;
          closeOpen();
          if (onSelect) {
            onSelect(name);
          }
        });

        list.appendChild(item);
      });
    };

    const showList = () => {
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      if (openSearch) {
        openSearch.close();
      }
      openSearch = api;
    };

    const open = () => {
      if (list.hidden) {
        // Показываем весь список (как при пустом поле), а не отфильтрованный
        // предзаполненной строкой — выпадашка ведёт себя одинаково на всех страницах.
        render('');
        showList();
        position();
      }
    };

    // Список позиционируем относительно окна (position: fixed), чтобы
    // прокручиваемый блок упражнений не обрезал его.
    const position = () => {
      const rect = input.getBoundingClientRect();
      const listHeight = Math.min(220, list.scrollHeight || 220);
      const spaceBelow = window.innerHeight - rect.bottom;
      const up = spaceBelow < listHeight + 8;
      wrapper.classList.toggle('ex-search-open-up', up);
      list.style.position = 'fixed';
      list.style.margin = '0';
      list.style.left = `${rect.left}px`;
      list.style.width = `${rect.width}px`;
      list.style.zIndex = '400';
      if (up) {
        list.style.top = 'auto';
        list.style.bottom = `${window.innerHeight - rect.top + 4}px`;
      } else {
        list.style.top = `${rect.bottom + 4}px`;
        list.style.bottom = 'auto';
      }
    };

    const close = () => {
      list.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      if (openSearch === api) {
        openSearch = null;
      }
    };

    const api = { open, close, position };

    input.addEventListener('focus', open);
    input.addEventListener('input', () => {
      render(input.value);
      if (list.hidden) {
        showList();
      }
      position();
    });
    input.addEventListener('blur', () => {
      setTimeout(close, 120);
    });

    input.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        close();
        return;
      }

      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        const items = Array.from(list.querySelectorAll('li.ex-search-option'));
        if (items.length === 0) {
          return;
        }
        event.preventDefault();
        const current = items.indexOf(document.activeElement);
        const step = event.key === 'ArrowDown' ? 1 : -1;
        items[(current + step + items.length) % items.length].focus();
        return;
      }

      if (event.key === 'Enter') {
        const active = list.querySelector('li:focus');
        if (active) {
          event.preventDefault();
          active.click();
        }
      }
    });

    return api;
  }

  window.setupExerciseSearch = setupExerciseSearch;
})();