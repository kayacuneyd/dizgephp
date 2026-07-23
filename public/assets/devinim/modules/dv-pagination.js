import{BaseComponent as s,html as i,define as o}from"../core.min.js";export class DvPagination extends s{static observedAttributes=["data-page","data-total","data-size"];initialState(){const t=Math.max(0,this.num("total",0)),a=Math.max(1,this.num("size",10));return{page:this.#t(this.num("page",1),t,a),total:t,size:a}}onAttribute(t,a){t==="data-total"&&(this.state.total=Math.max(0,Number(a)||0)),t==="data-size"&&(this.state.size=Math.max(1,Number(a)||1)),t==="data-page"&&this.goTo(Number(a)||1),t!=="data-page"&&(this.state.page=this.#t(this.state.page,this.state.total,this.state.size))}goToButton(t,a){this.goTo(Number(a.getAttribute("data-page")))}goTo(t){const a=this.#t(t,this.state.total,this.state.size);a!==this.state.page&&(this.state.page=a,this.emit("page",{page:a}))}template(){const t=Math.max(1,Math.ceil(this.state.total/this.state.size));return i`
      <nav aria-label="${this.str("label","Pagination")}">
        <button type="button" data-page="${this.state.page-1}" data-on:click="goToButton"
          disabled="${this.state.page<=1}">Previous</button>
        <span aria-current="page">Page ${this.state.page} of ${t}</span>
        <button type="button" data-page="${this.state.page+1}" data-on:click="goToButton"
          disabled="${this.state.page>=t}">Next</button>
      </nav>
    `}#t(t,a,e){return Math.min(Math.max(1,Math.ceil(a/e)||1),Math.max(1,Math.floor(t)||1))}}o("dv-pagination",DvPagination);
