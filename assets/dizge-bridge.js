const config = document.querySelector('script[type="application/json"][data-dizge-bridge]');
if (config) {
  const { endpoint, token, bindings } = JSON.parse(config.textContent);
  for (const binding of bindings) {
    const element = document.querySelector(binding.selector);
    element?.addEventListener(`dv:${binding.event}`, async (event) => {
      const response = await fetch(endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token }, body: JSON.stringify({ id: binding.id, detail: event.detail ?? {} }) });
      if (!response.ok) return;
      const { patch } = await response.json();
      for (const [key, value] of Object.entries(patch?.props ?? {})) {
        const attribute = `data-${key.replace(/[A-Z]/g, letter => `-${letter.toLowerCase()}`)}`;
        if (value === null || value === false) element.removeAttribute(attribute);
        else if (Array.isArray(value) || typeof value === 'object') element.setAttribute(attribute, JSON.stringify(value));
        else if (value === true) element.setAttribute(attribute, '');
        else element.setAttribute(attribute, String(value));
      }
    });
  }
}
