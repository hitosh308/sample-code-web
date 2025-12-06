// コピー機能と簡易なUIフィードバックを提供するスクリプト
// navigator.clipboard が使えない環境ではフォールバックとして textarea を利用する

document.addEventListener('DOMContentLoaded', () => {
  const buttons = document.querySelectorAll('.copy-btn');
  const tagSelect = document.querySelector('.tag-select');
  const tagControl = tagSelect?.querySelector('.tag-select__control');
  const tagMenu = tagSelect?.querySelector('.tag-select__menu');
  const tagFilterInput = tagSelect?.querySelector('.tag-select__filter');
  const tagOptionsContainer = tagSelect?.querySelector('.tag-select__options');
  const tagOptions = tagSelect ? Array.from(tagSelect.querySelectorAll('.tag-option')) : [];
  const tagCheckboxes = tagSelect ? Array.from(tagSelect.querySelectorAll('.tag-option input[type="checkbox"]')) : [];
  const searchForm = document.querySelector('.search-form');
  const languageSelect = document.querySelector('.language-select');
  const filterToggle = document.querySelector('.filter-toggle');
  const filterPanel = document.getElementById('search-panel');
  const panelBackdrop = document.getElementById('panel-backdrop');
  const panelClose = document.querySelector('.panel-close');
  const selectedTagButtons = document.querySelectorAll('.selected-tag');
  const clearTagsButton = document.querySelector('.clear-tags');

  buttons.forEach((button) => {
    button.addEventListener('click', async () => {
      const targetId = button.getAttribute('data-target');
      if (!targetId) return;

      const codeElement = document.getElementById(targetId);
      if (!codeElement) return;

      const codeText = codeElement.textContent ?? '';

      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(codeText);
        } else {
          fallbackCopy(codeText);
        }
        showCopied(button);
      } catch (error) {
        console.error('コピーに失敗しました', error);
        fallbackCopy(codeText);
        showCopied(button);
      }
    });
  });

  function updateTagControlLabel() {
    if (!tagControl) return;
    const selected = tagCheckboxes
      .filter((input) => input.checked)
      .map((input) => input.value);

    if (selected.length === 0) {
      tagControl.textContent = 'タグを選択';
    } else {
      const preview = selected.slice(0, 3).map((tag) => `#${tag}`);
      const more = selected.length > 3 ? `ほか${selected.length - 3}件` : '';
      tagControl.textContent = [preview.join(', '), more].filter(Boolean).join(' / ');
    }

    tagOptions.forEach((option) => {
      const input = option.querySelector('input');
      option.classList.toggle('is-active', Boolean(input?.checked));
    });
  }

  function toggleTagMenu(open) {
    if (!tagMenu || !tagControl) return;
    const shouldOpen = open ?? tagMenu.hidden;
    tagMenu.hidden = !shouldOpen;
    tagControl.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    if (shouldOpen) {
      tagFilterInput?.focus();
    }
  }

  tagControl?.addEventListener('click', () => toggleTagMenu());

  const tagFilterEmptyMessage = document.createElement('p');
  tagFilterEmptyMessage.className = 'tag-select__empty';
  tagFilterEmptyMessage.textContent = '該当するタグがありません';
  tagFilterEmptyMessage.hidden = true;
  if (tagOptionsContainer) {
    tagOptionsContainer.appendChild(tagFilterEmptyMessage);
  }

  function filterTagOptions(term = '') {
    if (!tagOptionsContainer) return;

    const normalizedTerm = term.trim().toLowerCase();
    let visibleCount = 0;

    tagOptions.forEach((option) => {
      const label = option.dataset.label?.toLowerCase() ?? option.textContent?.toLowerCase() ?? '';
      const isMatch = normalizedTerm === '' || label.includes(normalizedTerm);
      option.hidden = !isMatch;
      if (isMatch) {
        visibleCount += 1;
      }
    });

    tagFilterEmptyMessage.hidden = visibleCount > 0;
    tagOptionsContainer.scrollTo({ top: 0 });
  }

  tagFilterInput?.addEventListener('input', () => {
    filterTagOptions(tagFilterInput.value);
  });

  tagCheckboxes.forEach((input) => {
    input.addEventListener('change', () => {
      updateTagControlLabel();
      searchForm?.submit();
    });
  });

  selectedTagButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const tagValue = button.getAttribute('data-tag');
      const checkbox = tagSelect?.querySelector(`input[value="${CSS.escape(tagValue ?? '')}"]`);
      if (checkbox) {
        checkbox.checked = false;
        checkbox.closest('.tag-option')?.classList.remove('is-active');
      }
      updateTagControlLabel();
      searchForm?.submit();
    });
  });

  function resetTagSelections() {
    tagCheckboxes.forEach((input) => {
      input.checked = false;
      input.closest('.tag-option')?.classList.remove('is-active');
    });
    updateTagControlLabel();
    document.querySelector('.selected-tags')?.remove();
  }

  if (clearTagsButton) {
    clearTagsButton.addEventListener('click', () => {
      resetTagSelections();
      searchForm?.submit();
    });
  }

  document.addEventListener('click', (event) => {
    if (!tagSelect || !tagMenu || !tagControl) return;
    if (tagSelect.contains(event.target)) return;
    if (!tagMenu.hidden) {
      toggleTagMenu(false);
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      toggleTagMenu(false);
    }
  });

  updateTagControlLabel();

  filterTagOptions();

  languageSelect?.addEventListener('change', () => {
    resetTagSelections();
    searchForm?.submit();
  });

  if (window.hljs) {
    document.querySelectorAll('pre code').forEach((block) => {
      window.hljs.highlightElement(block);
    });
  }

  function togglePanel(open) {
    if (!filterPanel) return;
    const shouldOpen = open ?? !filterPanel.classList.contains('is-open');
    filterPanel.classList.toggle('is-open', shouldOpen);
    filterToggle?.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    if (panelBackdrop) {
      panelBackdrop.hidden = false;
      panelBackdrop.classList.toggle('is-active', shouldOpen);
      if (!shouldOpen) {
        setTimeout(() => {
          panelBackdrop.hidden = true;
        }, 180);
      }
    }
  }

  filterToggle?.addEventListener('click', () => togglePanel());
  panelClose?.addEventListener('click', () => togglePanel(false));
  panelBackdrop?.addEventListener('click', () => togglePanel(false));

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      togglePanel(false);
    }
  });
});

function fallbackCopy(text) {
  const textarea = document.createElement('textarea');
  textarea.value = text;
  textarea.setAttribute('readonly', '');
  textarea.style.position = 'absolute';
  textarea.style.left = '-9999px';
  document.body.appendChild(textarea);
  textarea.select();
  document.execCommand('copy');
  document.body.removeChild(textarea);
}

function showCopied(button) {
  const original = button.textContent;
  button.textContent = 'コピーしました';
  button.classList.add('copied');
  setTimeout(() => {
    button.textContent = original;
    button.classList.remove('copied');
  }, 2000);
}
