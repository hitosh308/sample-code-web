// コピー機能と簡易なUIフィードバックを提供するスクリプト
// navigator.clipboard が使えない環境ではフォールバックとして textarea を利用する

document.addEventListener('DOMContentLoaded', () => {
  const buttons = document.querySelectorAll('.copy-btn');
  const tagToggles = document.querySelectorAll('.tag-toggle input[type="checkbox"]');
  const searchForm = document.querySelector('.search-form');

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
