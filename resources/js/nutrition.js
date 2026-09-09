export const nutrients = [
  {key:'calories',label:'Calorias',unit:'kcal',color:'#cb704d'},
  {key:'protein',label:'Proteínas',unit:'g',color:'#285d49'},
  {key:'carbs',label:'Carboidratos',unit:'g',color:'#c49a3b'},
  {key:'fat',label:'Gorduras',unit:'g',color:'#9181a8'},
  {key:'fiber',label:'Fibras',unit:'g',color:'#5f9671'},
  {key:'sodium',label:'Sódio',unit:'mg',color:'#638aa1'},
];
export const format = (n,d=1) => n == null || !Number.isFinite(Number(n)) ? '—' : Number(n).toLocaleString('pt-BR',{maximumFractionDigits:d});
export const label = (s) => s?.replaceAll('_',' ') || '';
export const measureOptions = [{key:'mL',label:'mL',ml:1},{key:'cup',label:'Xícara de chá (200 mL)',ml:200},{key:'coffee_cup',label:'Xícara de café (50 mL)',ml:50},{key:'glass',label:'Copo médio (240 mL)',ml:240},{key:'goblet',label:'Taça (volume editável)',ml:150}];
export const consumedQuantity = (item, food) => {
  const quantity = Number(item.quantity) * Number(item.multiplier ?? 1);
  if (food?.unit !== 'mL') return quantity;
  const conversion = food.raw_values?.volume_conversion;
  const measure = item.measure ?? (conversion ? 'g' : 'mL');
  if (measure === 'g') return quantity / (conversion?.density_g_ml ?? 1);
  return quantity * (measure === 'goblet' ? Number(item.measure_ml ?? 150) : (measureOptions.find(m=>m.key===measure)?.ml ?? 1));
};
export function total(items, foods) {
  const result={quantity:0,totals:{},missing:{}};
  for(const n of nutrients){result.totals[n.key]=0;result.missing[n.key]=0;}
  for(const i of items){
    const f=foods.get(i.food_id);
    const quantity=consumedQuantity(i,f);
    if(!f || !Number.isFinite(quantity) || Number(i.quantity)<0 || Number(i.multiplier ?? 1)<0) continue;
    result.quantity+=quantity*(f.raw_values?.volume_conversion?.density_g_ml??1);
    for(const n of nutrients){
      if(f[n.key]==null && quantity>0) result.missing[n.key]++;
      else result.totals[n.key]+=Number(f[n.key]||0)*quantity/f.base_quantity;
    }
  }
  return result;
}
export function profile(p,activities){
  if(!p || !p.height || !p.weight || !p.age) return null;
  const male=p.sex==='Masculino';
  const bmi=p.weight/(p.height/100)**2;
  const bmr=male?66+13.7*p.weight+5*p.height-6.8*p.age:655+9.6*p.weight+1.8*p.height-4.7*p.age;
  const energy=bmr*(activities.find(a=>a.name===p.activity)?.factor||0);
  const target=energy+(p.goal==='Ganhar Peso'?Number(p.adjustment||0):p.goal==='Perder Peso'?-Number(p.adjustment||0):0);
  const limits=male?[20,25,30,40]:[19,24,29,39];
  return {bmi,bmr,energy,target,bmi_label:['Abaixo do Normal','Normal','Obesidade Leve','Obesidade Moderada','Obesidade Mórbida'][limits.filter(l=>bmi>=l).length]};
}
