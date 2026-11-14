// Simulação do protocolo q4_sync (4 clientes, latências aleatórias, 1 queda): verifica ausência de deadlock e determinismo.
const Q=[];function setTimeout_(t,f){Q.push([t,f]);}function fire(t){for(let i=0;i<Q.length;)if(Q[i][0]<=t){const f=Q.splice(i,1)[0][1];f();}else i++;}
const PD=45,DROP=20000;let seed=7;const rnd=()=>(seed=(seed*1664525+1013904223)>>>0)/4294967296;
const srv={t:{1:0,2:0,3:0,4:0},seen:{1:0,2:0,3:0,4:0},drop:{},moves:[]};let now=0;
function sync(slot,req){ // espelha o PHP (sem long-poll; o cliente já repete a cada 50 ms)
  if(!srv.drop[slot]){const tick=Math.max(srv.t[slot],req.tick);
    for(const c of req.cmds)if(c.tick>srv.t[slot]&&c.tick<=tick+200)srv.moves.push({id:srv.moves.length+1,slot,...c});
    srv.t[slot]=tick;srv.seen[slot]=now;
    for(const k in srv.t)if(!srv.drop[k]&&now-srv.seen[k]>DROP)srv.drop[k]=1;}
  const ticks={...srv.t},outs=Object.keys(srv.drop).map(Number); // ticks lidos ANTES dos lances
  return{ticks,outs,moves:srv.moves.filter(m=>m.slot!=slot&&m.id>req.since).map(m=>({...m}))};}
const cl=[1,2,3,4].map(s=>({s,tick:0,otick:0,since:0,cmds:{},out:[],hash:0,inflight:0,lat:40+rnd()*260,dead:false,stalls:0}));
const addCmd=(c,m)=>(c.cmds[m.tick]=c.cmds[m.tick]||[]).push(m);
function run(c){const l=c.cmds[c.tick];if(l){l.sort((a,b)=>a.slot-b.slot);for(const m of l)c.hash=(Math.imul(c.hash^(m.slot*131+m.v),16777619)+c.tick)>>>0;delete c.cmds[c.tick];}c.hash=(Math.imul(c.hash^1,16777619))>>>0;}
const END=900,DEADSLOT=3,DEADAT=300;let max=0;
for(now=0;now<400000;now+=33){
 for(const c of cl){if(c.dead||c.tick>=END)continue;
  if(c.s==DEADSLOT&&c.tick>=DEADAT){c.dead=true;continue;}
  let n=0;while(c.tick<c.otick+PD&&c.tick<END&&n<2){run(c);c.tick++;n++;}if(n==0)c.stalls++;
  if(rnd()<.03){const m={tick:c.tick+PD,v:Math.floor(rnd()*1000),slot:c.s};c.out.push({tick:m.tick,v:m.v});addCmd(c,m);}
  if(!c.inflight){c.inflight=1;const req={tick:c.tick,cmds:c.out,since:c.since};c.out=[];
   const d=c.lat*(.6+rnd()*.8);setTimeout_(now+d,()=>{const r=sync(c.s,req);setTimeout_(now+d,()=>{
     for(const m of r.moves){c.since=Math.max(c.since,m.id);addCmd(c,{tick:m.tick,v:m.v,slot:m.slot});}
     let mn=1e9;for(const k in r.ticks){if(+k==c.s||r.outs.includes(+k))continue;mn=Math.min(mn,r.ticks[k]);}
     c.otick=Math.max(c.otick,mn);c.inflight=0;});});}
 }
 fire(now);
 if(cl.every(c=>c.dead||c.tick>=END)){break;}
}
// (as funções acima são hoisted; Q é preenchida durante o laço)
console.log(cl.map(c=>`slot${c.s}: tick ${c.tick}${c.dead?' (caiu)':''} hash ${c.hash.toString(16)} paradas ${c.stalls}`).join('\n'));
const alive=cl.filter(c=>!c.dead);console.log('vivos chegaram ao fim:',alive.every(c=>c.tick>=END),'| tempo simulado',(now/1000).toFixed(1)+'s');
// determinismo: compara hash dos vivos no mesmo tick
console.log('hashes dos vivos iguais:',new Set(alive.map(c=>c.hash)).size==1);
