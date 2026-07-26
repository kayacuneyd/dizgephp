const picker = document.querySelector('[data-cms-media-picker]');

if (picker) {
  picker.addEventListener('click', (event) => {
    const button = event.target.closest('[data-media-url]');
    if (!button) return;
    event.preventDefault();

    const editor = document.querySelector('dv-rich-editor .dc-editor-canvas');
    const field = document.querySelector('textarea[name="body_html"]');
    if (!editor || !field) return;

    editor.focus();
    document.execCommand('insertImage', false, button.dataset.mediaUrl);
    const images = editor.querySelectorAll('img');
    const image = images[images.length - 1];
    if (image) image.alt = button.dataset.mediaAlt || '';
    field.value = editor.innerHTML;
    field.dispatchEvent(new Event('input', { bubbles: true }));
  });
}
