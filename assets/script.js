// コピー機能と簡易なUIフィードバックを提供するスクリプト
// navigator.clipboard が使えない環境ではフォールバックとして textarea を利用する

document.addEventListener('DOMContentLoaded', () => {
  const buttons = document.querySelectorAll('.copy-btn');
  const tagToggles = document.querySelectorAll('.tag-toggle input[type="checkbox"]');
  const searchForm = document.querySelector('.search-form');
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

  tagToggles.forEach((input) => {
    input.addEventListener('change', () => {
      const parentLabel = input.closest('.tag-toggle');
      if (parentLabel) {
        parentLabel.classList.toggle('is-active', input.checked);
      }

      if (searchForm) {
        searchForm.submit();
      }
    });
  });

  selectedTagButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const tagValue = button.getAttribute('data-tag');
      const checkbox = document.querySelector(`.tag-toggle input[value="${CSS.escape(tagValue ?? '')}"]`);
      if (checkbox) {
        checkbox.checked = false;
        checkbox.closest('.tag-toggle')?.classList.remove('is-active');
      }
      searchForm?.submit();
    });
  });

  if (clearTagsButton) {
    clearTagsButton.addEventListener('click', () => {
      tagToggles.forEach((input) => {
        input.checked = false;
        input.closest('.tag-toggle')?.classList.remove('is-active');
      });
      searchForm?.submit();
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
