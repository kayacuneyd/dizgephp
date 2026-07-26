import{BaseComponent as $,html as u,define as P}from"../core.min.js";var b=new Map,g=new Set,f=null,L="en";function c(o,t){b.set(o.toLowerCase(),t)}function x(){return f||document.documentElement.lang||L}function h(o){return g.add(o),()=>g.delete(o)}function n(o,t,e,a){let s=o.dataset[t],l=b.get(o.nodeName.toLowerCase())?.[x()]?.[t],i=s!==void 0?s:l!==void 0?l:e;return a?v(i,a):i}function v(o,t){return o.replace(/\{(\w+)\}/g,(e,a)=>Object.prototype.hasOwnProperty.call(t,a)?String(t[a]):e)}var m={en:{label:"Pagination",jumpLabel:"Jump to page",previousLabel:"Previous",nextLabel:"Next",previousPageLabel:"Previous page",nextPageLabel:"Next page",pageLabel:"Page {page}",jumpAriaLabel:"Jump to page, 1 to {pages}",goLabel:"Go"},tr:{label:"Sayfalama",jumpLabel:"Sayfaya git",previousLabel:"\xD6nceki",nextLabel:"Sonraki",previousPageLabel:"\xD6nceki sayfa",nextPageLabel:"Sonraki sayfa",pageLabel:"{page}. sayfa",jumpAriaLabel:"Sayfaya git, 1 ile {pages} aras\u0131",goLabel:"Git"}};c("dv-pagination",m);var d=Symbol("ellipsis"),r=class extends ${static observedAttributes=["data-page","data-total","data-size"];initialState(){let t=Math.max(0,this.num("total",0)),e=Math.max(1,this.num("size",10));return{page:this.#t(this.num("page",1),t,e),total:t,size:e}}connected(){this.onCleanup(h(()=>this.requestUpdate()))}onAttribute(t,e){t==="data-total"&&(this.state.total=Math.max(0,Number(e)||0)),t==="data-size"&&(this.state.size=Math.max(1,Number(e)||1)),t==="data-page"&&this.goTo(Number(e)||1),t!=="data-page"&&(this.state.page=this.#t(this.state.page,this.state.total,this.state.size))}goToButton(t,e){this.goTo(Number(e.getAttribute("data-page")))}jumpToPage(t,e){t.preventDefault();let a=e.querySelector("[data-pagination-jump-input]");a&&this.goTo(Number(a.value))}goTo(t){let e=this.#t(t,this.state.total,this.state.size);e!==this.state.page&&(this.state.page=e,this.emit("page",{page:e}))}template(){let t=Math.max(1,Math.ceil(this.state.total/this.state.size)),e=this.state.page;return u`
      <nav aria-label="${n(this,"label","Pagination")}">
        <button type="button" data-page="${e-1}" data-on:click="goToButton"
          aria-label="${n(this,"previousPageLabel","Previous page")}" disabled="${e<=1}">${n(this,"previousLabel","Previous")}</button>
        <ul class="dv-pagination-list">
          ${this.#e(e,t).map(a=>a===d?u`<li class="dv-pagination-ellipsis" aria-hidden="true">&hellip;</li>`:u`
              <li>
                <button type="button" data-page="${a}" data-on:click="goToButton"
                  aria-current="${a===e?"page":null}"
                  aria-label="${n(this,"pageLabel","Page {page}",{page:a})}">${a}</button>
              </li>
            `)}
        </ul>
        <span class="dv-pagination-status">Page ${e} of ${t}</span>
        <button type="button" data-page="${e+1}" data-on:click="goToButton"
          aria-label="${n(this,"nextPageLabel","Next page")}" disabled="${e>=t}">${n(this,"nextLabel","Next")}</button>
        <form class="dv-pagination-jump" data-on:submit="jumpToPage">
          <label>
            ${n(this,"jumpLabel","Jump to page")}
            <input type="number" inputmode="numeric" step="1" min="1" max="${t}" value="${e}"
              data-pagination-jump-input aria-label="${n(this,"jumpAriaLabel","Jump to page, 1 to {pages}",{pages:t})}">
          </label>
          <button type="submit">${n(this,"goLabel","Go")}</button>
        </form>
      </nav>
    `}#e(t,e){if(e<=7)return Array.from({length:e},(i,p)=>p+1);let a=[...new Set([1,e,t-2,t-1,t,t+1,t+2])].filter(i=>i>=1&&i<=e).sort((i,p)=>i-p),s=[],l=null;for(let i of a)l!==null&&i-l>1&&s.push(d),s.push(i),l=i;return s}#t(t,e,a){return Math.min(Math.max(1,Math.ceil(e/a)||1),Math.max(1,Math.floor(t)||1))}};P("dv-pagination",r);export{r as DvPagination};
