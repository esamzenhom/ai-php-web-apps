// Session changes must hide an already-rendered floating chat, including stale tabs.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const handlers={}, events={};
let frameSrc='/admin?embed=1', enabled=true, channel, poll;
const frame={getAttribute:()=>frameSrc,setAttribute:(_,value)=>{frameSrc=value;},removeAttribute:()=>{frameSrc=null;}};
const button={hidden:false,addEventListener:(name,fn)=>{handlers[name]=fn;}};
const popup={open:false,querySelector:()=>frame,showModal(){this.open=true;},close(){this.open=false;}};
class Channel{constructor(){channel=this;}}
const context={
 document:{hidden:false,getElementById:id=>({'open-chat':button,'floating-chat':popup}[id]),addEventListener:(name,fn)=>{events[name]=fn;}},
 window:{BroadcastChannel:Channel,addEventListener:(name,fn)=>{events[name]=fn;}},
 BroadcastChannel:Channel,location:{hash:''},
 fetch:async()=>({ok:true,json:async()=>({enabled})}),
 setInterval:fn=>{poll=fn;}
};
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname,'../admin/public/assets/home.js'),'utf8'),context);
(async()=>{
 await handlers.click();assert.equal(popup.open,true);
 enabled=false;channel.onmessage();
 assert.equal(button.hidden,true);assert.equal(popup.open,false);assert.equal(frameSrc,null);
 await poll();await handlers.click();assert.equal(popup.open,false);
 enabled=true;await poll();assert.equal(button.hidden,false);
 await handlers.click();assert.equal(frameSrc,'/admin?embed=1');
 enabled=false;await poll();assert.equal(button.hidden,true);assert.equal(popup.open,false);
 enabled=true;await poll();
 context.fetch=async()=>{throw new Error('offline');};
 await handlers.click();assert.equal(button.hidden,true);assert.equal(popup.open,false);
 console.log('PASS: logout broadcast, expiry/disabled setting, click recheck and failed session check hide/close chat');
})().catch(error=>{console.error(error);process.exitCode=1;});
