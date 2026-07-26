import{BaseComponent as g,html as v,define as $}from"../core.min.js";var l=new Map,c=new Set,f=null,p="en";function d(i,t){l.set(i.toLowerCase(),t)}function m(){return f||document.documentElement.lang||p}function u(i){return c.add(i),()=>c.delete(i)}function h(i,t,e,a){let s=i.dataset[t],r=l.get(i.nodeName.toLowerCase())?.[m()]?.[t],o=s!==void 0?s:r!==void 0?r:e;return a?x(o,a):o}function x(i,t){return i.replace(/\{(\w+)\}/g,(e,a)=>Object.prototype.hasOwnProperty.call(t,a)?String(t[a]):e)}var b={en:{label:"Tabs"},tr:{label:"Sekmeler"}};d("dv-tabs",b);var y=0,n=class extends g{static observedAttributes=["data-active"];#e=++y;#t=[];prepare(t){this.#t=t?Array.from(t.children).map((e,a)=>e.getAttribute("data-tab")??`Tab ${a+1}`):[]}initialState(){return{active:this.#a(this.num("active",0)),labels:[...this.#t]}}onAttribute(t,e){t==="data-active"&&this.activateIndex(Number(e)||0)}connected(){this.#i(),this.onCleanup(u(()=>this.requestUpdate()))}updated(t){t.includes("active")&&(this.#i(),this.#r()?.focus())}activate(t,e){this.activateIndex(Number(e.getAttribute("data-index")))}onKeydown(t){let e=this.state.labels.length-1;if(e<0)return;let a=null;switch(t.key){case"ArrowRight":a=this.state.active>=e?0:this.state.active+1;break;case"ArrowLeft":a=this.state.active<=0?e:this.state.active-1;break;case"Home":a=0;break;case"End":a=e;break;default:return}t.preventDefault(),this.activateIndex(a)}onFocusin(t,e){this.activateIndex(Number(e.getAttribute("data-index")))}activateIndex(t){let e=this.#a(t);e!==this.state.active&&(this.state.active=e,this.emit("tab",{index:e}))}template(){return v`
      <div role="tablist" aria-label="${h(this,"label","Tabs")}">
        ${this.state.labels.map((t,e)=>v`
          <button type="button" role="tab"
            id="${this.#n(e)}"
            aria-selected="${String(e===this.state.active)}"
            aria-controls="${this.#s(e)}"
            tabindex="${e===this.state.active?0:-1}"
            data-index="${e}"
            data-on:click="activate"
            data-on:keydown="onKeydown"
            data-on:focusin="onFocusin">${t}</button>
        `)}
      </div>
      <div class="dv-tabs-panels">${this.outlet}</div>
    `}#a(t){let e=this.#t.length-1;return!Number.isFinite(t)||t<0?0:Math.min(t,Math.max(e,0))}#i(){let t=this.querySelector("dv-outlet");t&&Array.from(t.children).forEach((e,a)=>{e.setAttribute("role","tabpanel"),e.id=this.#s(a),e.setAttribute("aria-labelledby",this.#n(a)),e.tabIndex=0,e.hidden=a!==this.state.active})}#r(){return this.querySelector('[role="tab"][aria-selected="true"]')}#n(t){return`dv-tabs-${this.#e}-tab-${t}`}#s(t){return`dv-tabs-${this.#e}-panel-${t}`}};$("dv-tabs",n);export{n as DvTabs};
