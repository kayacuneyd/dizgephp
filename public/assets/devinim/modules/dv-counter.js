import{component as e,html as n}from"../authoring.min.js";var s=e("dv-counter",{props:{start:0,step:1},state(){return{count:this.props.start}},sync:{start(t){this.state.count=t}},actions:{increment(){this.state.count+=this.props.step,this.emit("change",{count:this.state.count})},decrement(){this.state.count-=this.props.step,this.emit("change",{count:this.state.count})}},view(){return n`
      <button type="button" on:click="decrement" aria-label="Decrease">−</button>
      <output aria-live="polite">${this.state.count}</output>
      <button type="button" on:click="increment" aria-label="Increase">+</button>
    `}});export{s as DvCounter};
