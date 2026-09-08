'use strict';
const popup=document.getElementById('floating-chat');
const chatButton=document.getElementById('open-chat');
if(chatButton && popup){
 const frame=popup.querySelector('iframe'), chatUrl=frame.getAttribute('src');
 let revision=0;
 function hideChat(){
  revision++;
  chatButton.hidden=true;
  if(popup.open)popup.close();
  frame.removeAttribute('src');
 }
 async function checkChat(){
  const current=++revision;
  try{
   const response=await fetch('/_chat-status',{cache:'no-store'});
   const state=response.ok?await response.json():null;
   if(current!==revision)return false;
   if(state?.enabled!==true){hideChat();return false;}
   chatButton.hidden=false;
   return true;
  }catch{if(current===revision)hideChat();return false;}
 }
 chatButton.addEventListener('click',async()=>{
  if(await checkChat()){frame.setAttribute('src',chatUrl);popup.showModal();}
 });
 window.addEventListener('focus',checkChat);
 window.addEventListener('pageshow',()=>{hideChat();checkChat();});
 document.addEventListener('visibilitychange',()=>{if(!document.hidden)checkChat();});
 if('BroadcastChannel' in window){
  const channel=new BroadcastChannel('builder-session');
  channel.onmessage=()=>{hideChat();checkChat();};
 }
 setInterval(checkChat,15000);
}
document.getElementById('close-chat')?.addEventListener('click',()=>popup.close());
if(location.hash.startsWith('#setup=')){
 const token=location.hash.slice(7);
 history.replaceState(null,'',location.pathname);
 const link=document.querySelector('a[href="/admin"]');
 if(link && /^[a-f0-9]{48}$/.test(token)) link.href='/admin#setup='+token;
}
