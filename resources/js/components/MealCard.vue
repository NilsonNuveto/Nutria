<script setup>
import { computed } from 'vue';
import PortionInput from './PortionInput.vue';
import { Plus, Trash2, Clock3, X } from 'lucide-vue-next';
import {total,itemTotal,format,nutrients,label,consumedQuantity} from '../nutrition';
const props=defineProps({meal:Object,index:Number,foods:Map,types:Array});
const emit=defineEmits(['add','add-alternative','remove']);
const summary=computed(()=>total(props.meal.items,props.foods));
const calories=option=>{const food=props.foods.get(option.food_id);return food?.calories==null?null:food.calories*consumedQuantity(option,food)/food.base_quantity;};
</script>
<style scoped>
.meal-item.with-units { display: block; position: relative; padding-right: 42px; }
.meal-option { display: grid; grid-template-columns: minmax(150px,1fr) minmax(230px,1.35fr) 72px; gap: 14px; align-items: center; }
.portion-controls { display: grid; grid-template-columns: minmax(0, 1fr) 16px 72px; gap: 7px 8px; align-items: end; width: 100%; }
.portion-controls label { font-size: .75rem; color: #647456; gap: 5px; }
.portion-controls input { width: 72px; min-height: 40px; padding: 7px; font-size: .875rem; }
.multiply-sign { align-self: center; padding-top: 18px; color: #7a866f; }
.consumed-quantity { grid-column: 1 / -1; color: #647456; font-size: .75rem; }
.remove-item { position: absolute; right: 0; top: 18px; }
.alternative-divider { display: flex; align-items: center; gap: 10px; margin: 12px 0; color: #6f815f; font-size: .68rem; font-weight: 700; letter-spacing: .12em; }
.alternative-divider::before,.alternative-divider::after { content: ''; height: 1px; flex: 1; background: #e3e9da; }
.alternative-option { background: #f8faf5; border-radius: 10px; padding: 12px; }
.alternative-option .icon-button { justify-self: end; }
.add-alternative { margin-top: 9px; font-size: .76rem; }
.item-average { margin-top: 11px; text-align: right; color: #647456; font-size: .72rem; }
.item-average strong { color: #3e5e48; }
@media (max-width: 760px) {
  .meal-item.with-units { display: block; padding-right: 0; }
  .meal-option { grid-template-columns: minmax(0,1fr) 34px; gap: 10px; }
  .meal-option .food-name { grid-column: 1; grid-row: 1; }
  .meal-option .portion-controls { grid-column: 1 / -1; grid-row: 2; }
  .meal-option .item-calories { grid-column: 1; grid-row: 3; text-align: left; }
  .alternative-option .icon-button { grid-column: 2; grid-row: 1; }
  .remove-item { right: 0; top: 12px; }
  .meal-option:first-child { padding-right: 38px; }
  .item-average { text-align: left; }
}
@media print { .portion-controls input { border: 0; background: none; } .alternative-option { background: none; padding: 0; } .add-alternative,.remove-item,.alternative-option .icon-button { display: none!important; } }
</style>
<template>
<section class="meal-card">
  <div class="meal-heading"><span class="meal-number">{{String(index+1).padStart(2,'0')}}</span><div class="meal-title"><select v-model="meal.name" :aria-label="`Tipo da refeição ${index+1}`"><option v-for="t in types" :key="t">{{t}}</option></select><label class="time"><Clock3 :size="14"/><input type="time" v-model="meal.time" :aria-label="`Horário da refeição ${index+1}`"/></label></div><strong class="meal-calories">{{summary.missing.calories?'≥ ':''}}{{format(summary.totals.calories,0)}} <small>kcal</small></strong><button class="icon-button remove-meal" @click="emit('remove')" aria-label="Remover refeição"><Trash2 :size="16"/></button></div>
  <div v-if="meal.items.length" class="meal-items">
    <div class="meal-item with-units" v-for="(item,i) in meal.items" :key="i">
      <div class="meal-option">
        <div class="food-name"><strong>{{foods.get(item.food_id)?.name}}</strong><small>{{label(foods.get(item.food_id)?.category)}}</small></div>
        <div class="portion-controls">
          <div><small>Qtd. por unidade</small><PortionInput :item="item" :food="foods.get(item.food_id)" /></div>
          <span class="multiply-sign" aria-hidden="true">×</span>
          <label>Unidades <input :value="item.multiplier ?? 1" @input="item.multiplier=$event.target.value === '' ? '' : Number($event.target.value)" type="number" min="0" max="10000" step="0.1" required :aria-label="`Unidades de ${foods.get(item.food_id)?.name}`"/></label>
          <small class="consumed-quantity">Total: {{format(consumedQuantity(item,foods.get(item.food_id)))}} {{foods.get(item.food_id)?.unit}}</small>
        </div>
        <span class="item-calories">{{format(calories(item),0)}}<small> kcal</small></span>
      </div>
      <template v-if="item.alternative">
        <div class="alternative-divider"><span>OU</span></div>
        <div class="meal-option alternative-option">
          <div class="food-name"><strong>{{foods.get(item.alternative.food_id)?.name}}</strong><small>{{label(foods.get(item.alternative.food_id)?.category)}}</small></div>
          <div class="portion-controls">
            <div><small>Qtd. por unidade</small><PortionInput :item="item.alternative" :food="foods.get(item.alternative.food_id)" /></div>
            <span class="multiply-sign" aria-hidden="true">×</span>
            <label>Unidades <input :value="item.alternative.multiplier ?? 1" @input="item.alternative.multiplier=$event.target.value === '' ? '' : Number($event.target.value)" type="number" min="0" max="10000" step="0.1" required :aria-label="`Unidades de ${foods.get(item.alternative.food_id)?.name}`"/></label>
            <small class="consumed-quantity">Total: {{format(consumedQuantity(item.alternative,foods.get(item.alternative.food_id)))}} {{foods.get(item.alternative.food_id)?.unit}}</small>
          </div>
          <span class="item-calories">{{format(calories(item.alternative),0)}}<small> kcal</small></span>
          <button class="icon-button" @click="delete item.alternative" :aria-label="`Remover alternativa ${foods.get(item.alternative.food_id)?.name}`"><X :size="17"/></button>
        </div>
        <div class="item-average">Média das opções: <strong>{{itemTotal(item,foods).missing.calories?'≥ ':''}}{{format(itemTotal(item,foods).totals.calories,0)}} kcal</strong></div>
      </template>
      <button v-else class="text-button add-alternative" @click="emit('add-alternative',i)"><Plus :size="15"/> Adicionar opção “ou”</button>
      <button class="icon-button remove-item" @click="meal.items.splice(i,1)" :aria-label="`Remover ${foods.get(item.food_id)?.name}`"><X :size="17"/></button>
    </div>
  </div>
  <p v-else class="empty-meal">Esta refeição ainda não tem alimentos.</p>
  <div class="meal-footer"><button class="text-button" @click="emit('add')"><Plus :size="16"/> Adicionar alimento</button><div class="mini-nutrients"><span v-for="n in nutrients.slice(1,4)" :key="n.key"><i :style="{background:n.color}"></i>{{n.label}} <b>{{summary.missing[n.key]?'≥ ':''}}{{format(summary.totals[n.key])}} g</b></span></div></div>
  <details v-if="meal.items.length" class="meal-detail"><summary>Ver todos os nutrientes</summary><div class="table-scroll"><table><thead><tr><th>Alimento / alternativas</th><th v-for="n in nutrients" :key="n.key">{{n.label}} ({{n.unit}})</th></tr></thead><tbody><tr v-for="(i,itemIndex) in meal.items" :key="itemIndex"><td>{{foods.get(i.food_id)?.name}}<template v-if="i.alternative"> <b>ou</b> {{foods.get(i.alternative.food_id)?.name}}</template><small>{{i.alternative?'Média das duas opções':'Valor da porção escolhida'}}</small></td><td v-for="n in nutrients" :key="n.key">{{itemTotal(i,foods).missing[n.key]?'≥ ':''}}{{format(itemTotal(i,foods).totals[n.key])}}</td></tr><tr><th>Total</th><th v-for="n in nutrients" :key="n.key">{{summary.missing[n.key]?'≥ ':''}}{{format(summary.totals[n.key])}}</th></tr></tbody></table></div></details>
</section>
</template>
