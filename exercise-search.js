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

  // Пока список открыт, пересчитываем его высоту/направление при изменении
  // размера окна или открытии клавиатуры (visualViewport).
  const repositionOpen = () => {
    if (openSearch && openSearch.position) {
      openSearch.position();
    }
  };
  window.addEventListener('resize', repositionOpen);
  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', repositionOpen);
  }

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

    // Список держим в потоке (абсолютно относительно .ex-search), чтобы на телефоне
    // он двигался вместе с полем, когда браузер подстраивается под клавиатуру.
    // Высоту подгоняем под реально доступное место, чтобы результат не прятался.
    const position = () => {
      list.style.position = '';
      list.style.margin = '';
      list.style.left = '';
      list.style.width = '';
      list.style.zIndex = '';

      const rect = input.getBoundingClientRect();
      const vv = window.visualViewport;
      const vTop = vv ? vv.offsetTop : 0;
      const vBottom = vv ? vv.offsetTop + vv.height : window.innerHeight;

      const builder = wrapper.closest('.exercise-builder');
      const bRect = builder ? builder.getBoundingClientRect() : null;

      const spaceBelowVisual = vBottom - rect.bottom;
      const spaceAboveVisual = rect.top - vTop;
      const builderSpaceBelow = bRect ? bRect.bottom - rect.bottom : Infinity;
      const builderSpaceAbove = bRect ? rect.top - bRect.top : Infinity;

      // Сначала решаем, куда открывать: вниз, если в прокручиваемом блоке есть место;
      // иначе — вверх (чтобы не обрезало краем блока).
      const wantDown = builderSpaceBelow >= 160;
      const up = !wantDown && Math.min(builderSpaceAbove, spaceAboveVisual) >= 160;
      wrapper.classList.toggle('ex-search-open-up', up);

      // Высоту ограничиваем доступным местом, чтобы список был виден над клавиатурой.
      let maxHeight;
      if (up) {
        maxHeight = Math.min(220, builderSpaceAbove - 8, spaceAboveVisual - 8);
      } else {
        maxHeight = Math.min(220, builderSpaceBelow - 8, spaceBelowVisual - 8);
      }
      list.style.maxHeight = `${Math.max(72, maxHeight)}px`;

      // Подкручиваем прокрутку построителя, чтобы список оказался в видимой зоне.
      if (bRect) {
        const lRect = list.getBoundingClientRect();
        if (lRect.top < bRect.top + 4 && builder.scrollTop > 0) {
          builder.scrollTop -= Math.ceil(bRect.top + 4 - lRect.top);
        }
        if (lRect.bottom > bRect.bottom - 4) {
          builder.scrollTop += Math.ceil(lRect.bottom - (bRect.bottom - 4) + 8);
        }
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