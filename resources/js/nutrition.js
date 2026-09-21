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
const activityLevel = activity => ({'Sedentário':'inactive','Levemente Ativo':'low','Moderadamente Ativo':'active','Bastante Ativo':'very','Muito Ativo':'very'})[activity]||'inactive';
function estimatedEnergy(p,male){
  const age=Number(p.age),height=Number(p.height),weight=Number(p.weight),level=activityLevel(p.activity);
  if(age<3) return male?-716.45-age+17.82*height+15.06*weight+20:-69.15+80*age+2.65*height+54.15*weight+15;
  const equations=age<19?{
    male:{inactive:[-447.51,3.68,13.01,13.15],low:[19.12,3.68,8.62,20.28],active:[-388.19,3.68,12.66,20.46],very:[-671.75,3.68,15.38,23.25]},
    female:{inactive:[55.59,-22.25,8.43,17.07],low:[-297.54,-22.25,12.77,14.73],active:[-189.55,-22.25,11.74,18.34],very:[-709.59,-22.25,18.22,14.25]},
  }:{
    male:{inactive:[753.07,-10.83,6.50,14.10],low:[581.47,-10.83,8.30,14.94],active:[1004.82,-10.83,6.52,15.91],very:[-517.88,-10.83,15.61,19.11]},
    female:{inactive:[584.90,-7.01,5.72,11.71],low:[575.77,-7.01,6.60,12.14],active:[710.25,-7.01,6.54,12.34],very:[511.83,-7.01,9.07,12.56]},
  };
  const [base,ageCoefficient,heightCoefficient,weightCoefficient]=equations[male?'male':'female'][level];
  const growth=age<19?(age===3?(male?20:15):age<=8?15:age<=13?(male?25:30):20):0;
  return base+ageCoefficient*age+heightCoefficient*height+weightCoefficient*weight+growth;
}
function bmiLabel(bmi,age){
  if(age<19) return 'Avaliar por curva de crescimento';
  if(bmi<18.5) return 'Abaixo do peso';
  if(bmi<25) return 'Eutrofia';
  if(bmi<30) return 'Sobrepeso';
  if(bmi<35) return 'Obesidade grau I';
  if(bmi<40) return 'Obesidade grau II';
  return 'Obesidade grau III';
}
function macroTargets(p,target,level){
  const age=Number(p.age),weight=Number(p.weight),targetWeight=Number(p.target_weight);
  const adult=age>=19;
  let proteinPercentage=15;
  let protein=target*.15/4;
  let fatPercentage=age<=3?35:30;
  if(adult){
    const referenceWeight=p.goal==='Perder Peso'&&targetWeight>0&&targetWeight<weight?targetWeight:weight;
    const gramsPerKg=p.goal==='Perder Peso'?1.3:p.goal==='Ganhar Peso'?1.6:level==='inactive'?.8:1.2;
    protein=Math.max(referenceWeight*gramsPerKg,target*.10/4);
    protein=Math.min(protein,target*.35/4);
    proteinPercentage=protein*4/target*100;
    fatPercentage=Math.max(20,Math.min(30,55-proteinPercentage));
  }
  const fat=target*(fatPercentage/100)/9;
  const carbs=Math.max(0,(target-protein*4-fat*9)/4);
  const carbsPercentage=carbs*4/target*100;
  const ranges=adult?{protein:[10,35],carbs:[45,65],fat:[20,35]}:age<=3?{protein:[5,20],carbs:[45,65],fat:[30,40]}:{protein:[10,30],carbs:[45,65],fat:[25,35]};
  return {
    protein:{grams:protein,percentage:proteinPercentage,grams_per_kg:protein/weight,range_grams:[target*ranges.protein[0]/400,target*ranges.protein[1]/400]},
    carbs:{grams:carbs,percentage:carbsPercentage,range_grams:[target*ranges.carbs[0]/400,target*ranges.carbs[1]/400]},
    fat:{grams:fat,percentage:fatPercentage,range_grams:[target*ranges.fat[0]/900,target*ranges.fat[1]/900]},
    fiber:{grams:target*14/1000},
  };
}
export function profile(p){
  if(!p || !p.height || !p.weight || !p.age) return null;
  const male=p.sex==='Masculino',age=Number(p.age),weight=Number(p.weight),height=Number(p.height);
  const bmi=weight/(height/100)**2,level=activityLevel(p.activity),energy=estimatedEnergy(p,male);
  const hasAdjustment=p.adjustment!==null&&p.adjustment!==''&&p.adjustment!==undefined;
  const adjustment=hasAdjustment?Number(p.adjustment):p.goal==='Perder Peso'?500:p.goal==='Ganhar Peso'?energy*.05:0;
  const energyAdjustment=p.goal==='Perder Peso'?-adjustment:p.goal==='Ganhar Peso'?adjustment:0;
  const target=energy+energyAdjustment;
  const bodyFat=Number(p.body_fat),waist=Number(p.waist),targetWeight=Number(p.target_weight);
  const result={bmi,bmi_label:bmiLabel(bmi,age),energy,target,energy_adjustment:energyAdjustment,automatic_adjustment:!hasAdjustment,method:'NASEM EER 2023',macros:target>0?macroTargets(p,target,level):null};
  if(bodyFat>0){result.fat_mass=weight*bodyFat/100;result.lean_mass=weight-result.fat_mass;}
  if(waist>0){
    result.waist_height_ratio=waist/height;
    result.waist_height_label=age<5||bmi>=35?'Interpretar clinicamente':result.waist_height_ratio<.4?'Abaixo da faixa de referência':result.waist_height_ratio<.5?'Faixa saudável':result.waist_height_ratio<.6?'Adiposidade central aumentada':'Adiposidade central alta';
  }
  if(targetWeight>0) result.weight_delta=targetWeight-weight;
  return result;
}
