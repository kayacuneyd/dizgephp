import{BaseComponent as s,html as i,define as o}from"../core.min.js";let n=0;export class DvDisclosure extends s{static observedAttributes=["data-open"];#t=++n;initialState(){return{open:this.bool("open",!1)}}onAttribute(t,e){t==="data-open"&&(this.state.open=e!==null&&e!=="false"&&e!=="0")}toggle(){this.state.open=!this.state.open,this.emit("toggle",{open:this.state.open})}template(){const t=`dv-disclosure-${this.#t}-panel`;return i`
      <button type="button" aria-expanded="${String(this.state.open)}" aria-controls="${t}"
        data-on:click="toggle">${this.str("summary","Details")}</button>
      <div id="${t}" hidden="${!this.state.open}">${this.outlet}</div>
    `}}o("dv-disclosure",DvDisclosure);
