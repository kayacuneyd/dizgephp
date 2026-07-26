const form = document.querySelector('.dc-form');
if (form) {
  const key = `dizgecms:draft:${form.action}`;
  const fields = ['title', 'slug', 'meta_description', 'body_html'];
  const save = () => {
    const data = Object.fromEntries(fields.map(name => [name, form.elements.namedItem(name)?.value || '']));
    localStorage.setItem(key, JSON.stringify({savedAt: Date.now(), data}));
  };
  const raw = localStorage.getItem(key);
  if (raw) try {
    const cached = JSON.parse(raw);
    if (cached?.data && window.confirm('Kaydedilmemiş yerel taslak bulundu. Geri yüklensin mi?')) {
      for (const name of fields) { const field = form.elements.namedItem(name); if (field && typeof cached.data[name] === 'string') field.value = cached.data[name]; }
      const editor = form.querySelector('.dc-editor-canvas'); if (editor) editor.innerHTML = cached.data.body_html || '';
    }
  } catch { localStorage.removeItem(key); }
  let timer; form.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(save, 800); });
  form.addEventListener('submit', () => localStorage.removeItem(key));
}
