import{BaseComponent as s,html as i,define as r}from"../core.min.js";let n=0;export class DvTabs extends s{static observedAttributes=["data-active"];#e=++n;#t=[];prepare(e){this.#t=e?Array.from(e.children).map((t,a)=>t.getAttribute("data-tab")??`Tab ${a+1}`):[]}initialState(){return{active:this.#a(this.num("active",0)),labels:[...this.#t]}}onAttribute(e,t){e==="data-active"&&this.activateIndex(Number(t)||0)}connected(){this.#i()}updated(e){e.includes("active")&&(this.#i(),this.#n()?.focus())}activate(e,t){this.activateIndex(Number(t.getAttribute("data-index")))}onKeydown(e){const t=this.state.labels.length-1;if(t<0)return;let a=null;switch(e.key){case"ArrowRight":a=this.state.active>=t?0:this.state.active+1;break;case"ArrowLeft":a=this.state.active<=0?t:this.state.active-1;break;case"Home":a=0;break;case"End":a=t;break;default:return}e.preventDefault(),this.activateIndex(a)}onFocusin(e,t){this.activateIndex(Number(t.getAttribute("data-index")))}activateIndex(e){const t=this.#a(e);t!==this.state.active&&(this.state.active=t,this.emit("tab",{index:t}))}template(){return i`
      <div role="tablist" aria-label="${this.str("label","Tabs")}">
        ${this.state.labels.map((e,t)=>i`
          <button type="button" role="tab"
            id="${this.#s(t)}"
            aria-selected="${String(t===this.state.active)}"
            aria-controls="${this.#r(t)}"
            tabindex="${t===this.state.active?0:-1}"
            data-index="${t}"
            data-on:click="activate"
            data-on:keydown="onKeydown"
            data-on:focusin="onFocusin">${e}</button>
        `)}
      </div>
      <div class="dv-tabs-panels">${this.outlet}</div>
    `}#a(e){const t=this.#t.length-1;return!Number.isFinite(e)||e<0?0:Math.min(e,Math.max(t,0))}#i(){const e=this.querySelector("dv-outlet");e&&Array.from(e.children).forEach((t,a)=>{t.setAttribute("role","tabpanel"),t.id=this.#r(a),t.setAttribute("aria-labelledby",this.#s(a)),t.tabIndex=0,t.hidden=a!==this.state.active})}#n(){return this.querySelector('[role="tab"][aria-selected="true"]')}#s(e){return`dv-tabs-${this.#e}-tab-${e}`}#r(e){return`dv-tabs-${this.#e}-panel-${e}`}}r("dv-tabs",DvTabs);
