import { component, html, unsafe } from '/assets/devinim/authoring.min.js';

component('dv-rich-editor', {
  props: { name: 'body_html', value: '' },
  view() {
    return html`<section class="dc-editor" aria-label="Zengin metin editörü">
      <div class="dc-editor-toolbar" role="toolbar" aria-label="Biçim araçları">
        <button type="button" data-command="formatBlock" data-argument="p" on:click="format">Paragraf</button>
        <button type="button" data-command="formatBlock" data-argument="h2" on:click="format">H2</button>
        <button type="button" data-command="formatBlock" data-argument="h3" on:click="format">H3</button>
        <button type="button" data-command="bold" on:click="format"><strong>Kalın</strong></button>
        <button type="button" data-command="italic" on:click="format"><em>İtalik</em></button>
        <button type="button" data-command="insertUnorderedList" on:click="format">Liste</button>
        <button type="button" data-command="formatBlock" data-argument="blockquote" on:click="format">Alıntı</button>
        <button type="button" data-command="formatBlock" data-argument="pre" on:click="format">Kod</button>
        <button type="button" on:click="link">Link</button>
        <button type="button" on:click="image">Görsel</button>
        <button type="button" data-command="undo" on:click="format">Geri al</button>
        <button type="button" data-command="redo" on:click="format">Yinele</button>
      </div>
      <div class="dc-editor-canvas" contenteditable="true" role="textbox" aria-multiline="true" data-on:input="sync">${unsafe(this.props.value)}</div>
    </section>`;
  },
  actions: {
    format(event, button) {
      event.preventDefault();
      this.querySelector('.dc-editor-canvas').focus();
      document.execCommand(button.dataset.command, false, button.dataset.argument || null);
      this.sync();
    },
    link(event) {
      event.preventDefault();
      const href = window.prompt('https:// veya mailto: ile başlayan bağlantı girin');
      if (!href) return;
      this.querySelector('.dc-editor-canvas').focus();
      document.execCommand('createLink', false, href);
      this.sync();
    },
    image(event) {
      event.preventDefault();
      const src = window.prompt('Studio’dan yüklenmiş /demo/media/ID URL’sini girin');
      if (!src || !/^\/demo\/media\/[1-9][0-9]*$/.test(src)) return;
      this.querySelector('.dc-editor-canvas').focus();
      document.execCommand('insertImage', false, src);
      this.sync();
    },
    sync() {
      const field = this.closest('form')?.querySelector(`textarea[name="${CSS.escape(this.props.name)}"]`);
      const editor = this.querySelector('.dc-editor-canvas');
      if (field && editor) field.value = editor.innerHTML;
    },
  },
  connected() { this.sync(); },
  updated() { this.sync(); },
});
