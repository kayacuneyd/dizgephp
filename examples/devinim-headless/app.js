const base = document.body.dataset.apiBase.replace(/\/$/, '');
const api = async (path) => {
  const response = await fetch(`${base}/api/v1${path}`, {headers: {Accept: 'application/json'}});
  if (!response.ok) throw new Error(`API ${response.status}`);
  return (await response.json()).data;
};
const state = document.querySelector('#state');
const app = document.querySelector('#app');
const esc = (value) => String(value).replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));

function show(html, title, description = '') {
  document.title = title;
  document.querySelector('meta[name="description"]').content = description;
  state.hidden = true; app.hidden = false; app.innerHTML = html;
}
function href(url) { return `#${url}`; }
function cmsHtml(value) { return String(value).replace(/(src|href)="\/(media)\//g, `$1="${base}/$2/`); }

async function renderMenu() {
  const menu = await api('/menus/primary');
  const children = new Map();
  menu.items.forEach(item => { const key = item.parent_id || 0; children.set(key, [...(children.get(key) || []), item]); });
  const render = (parent = 0) => (children.get(parent) || []).map(item => `<li><a href="${href(item.url)}">${esc(item.label)}</a>${children.has(item.id) ? `<ul>${render(item.id)}</ul>` : ''}</li>`).join('');
  document.querySelector('#menu').innerHTML = `<ul>${render()}</ul>`;
}
async function renderRoute() {
  const path = location.hash.slice(1) || '/';
  state.hidden = false; state.dataset.state = 'loading'; app.hidden = true;
  try {
    if (path === '/') {
      const site = await api('/site');
      document.querySelector('#brand').textContent = site.name;
      if (site.home_page) return location.replace(href(site.home_page.url));
      return show(`<h1>${esc(site.name)}</h1><p class="muted">${esc(site.description)}</p>`, site.name, site.description);
    }
    if (path.startsWith('/search')) {
      const query = new URLSearchParams(path.split('?')[1] || '').get('q') || '';
      const results = await api(`/search?q=${encodeURIComponent(query)}`);
      return show(`<h1>Arama</h1><p>${esc(query)}</p><ul>${results.map(item => `<li><a href="${href(item.url)}">${esc(item.title)}</a><p>${esc(item.excerpt || '')}</p></li>`).join('')}</ul>`, `Arama: ${query}`);
    }
    const match = path.match(/^\/(pages|articles)\/([^/]+)$/);
    if (!match) throw new Error('Not found');
    const content = await api(`/${match[1]}/${encodeURIComponent(match[2])}`);
    show(`<article><h1>${esc(content.title)}</h1><div>${cmsHtml(content.body_html)}</div></article>`, content.title, content.meta_description);
  } catch (error) {
    state.hidden = false; state.dataset.state = 'error';
  }
}

window.addEventListener('hashchange', renderRoute);
document.querySelector('#search-form').addEventListener('submit', event => { event.preventDefault(); const query = document.querySelector('#search-query').value.trim(); location.hash = `/search?q=${encodeURIComponent(query)}`; });
try { await renderMenu(); await renderRoute(); } catch { state.dataset.state = 'error'; }
