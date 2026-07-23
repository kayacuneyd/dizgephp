import{BaseComponent as o,html as s,define as i}from"../core.min.js";let a=0;export class DvModal extends o{static observedAttributes=["data-open"];#s=++a;#t=null;initialState(){return{open:this.bool("open",!1)}}connected(){this.state.open&&this.#o()}updated(t){t.includes("open")&&this.state.open&&this.#o(),t.includes("open")&&!this.state.open&&this.#t?.focus()}onAttribute(t,e){t==="data-open"&&(this.state.open=e!==null&&e!=="false"&&e!=="0")}open(t,e){this.#t=e instanceof HTMLElement?e:document.activeElement,this.#e(!0)}close(){this.#e(!1)}onKeydown(t){t.key==="Escape"&&(t.preventDefault(),this.close())}#e(t){t!==this.state.open&&(this.state.open=t,this.emit(t?"open":"close"))}#o(){queueMicrotask(()=>this.querySelector('[role="dialog"]')?.focus())}template(){const t=`dv-modal-${this.#s}-title`;return s`
      <div class="dv-modal-backdrop" hidden="${!this.state.open}">
        <section class="dv-modal" role="dialog" aria-modal="true" aria-labelledby="${t}"
          tabindex="-1" data-on:keydown="onKeydown">
          <header>
            <h2 id="${t}">${this.str("label","Dialog")}</h2>
            <button type="button" aria-label="Close" data-on:click="close">×</button>
          </header>
          <div class="dv-modal-content">${this.outlet}</div>
        </section>
      </div>
    `}}i("dv-modal",DvModal);
