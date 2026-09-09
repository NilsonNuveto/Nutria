<script setup>
import { computed } from 'vue';
import PortionInput from './PortionInput.vue';
import { Plus, Trash2, Clock3 } from 'lucide-vue-next';
import {total,format,nutrients,label,consumedQuantity} from '../nutrition';
const props=defineProps({meal:Object,index:Number,foods:Map,types:Array});
const emit=defineEmits(['add','remove']);
const summary=computed(()=>total(props.meal.items,props.foods));
</script>
<style scoped>
.meal-item.with-units { flex-wrap: wrap; }
.with-units .food-name { flex-basis: 100%; }
.portion-controls { display: grid; grid-template-columns: minmax(0, 1fr) 16px 72px; gap: 7px 8px; align-items: end; margin-right: auto; width: 100%; }
.portion-controls label { font-size: .75rem; color: #647456; gap: 5px; }
.portion-controls input { width: 72px; min-height: 40px; padding: 7px; font-size: .875rem; }
.multiply-sign { align-self: center; padding-top: 18px; color: #7a866f; }
.consumed-quantity { grid-column: 1 / -1; color: #647456; font-size: .75rem; }
@media (max-width: 760px) {
  .meal-item.with-units { grid-template-columns: minmax(0, 1fr) 35px; }
  .with-units .food-name { grid-column: 1; grid-row: 1; }
  .with-units > .icon-button { grid-column: 2; grid-row: 1; }
  .with-units .portion-controls { grid-column: 1 / -1; grid-row: 2; }
  .with-units .item-calories { grid-column: 1 / -1; grid-row: 3; text-align: left; }
}
@media print { .portion-controls input { border: 0; background: none; } }
</style>
<template>
<section class="meal-card">
  <div class="meal-heading"><span class="meal-number">{{String(index+1).padStart(2,'0')}}</span><div class="meal-title"><select v-model="meal.name" :aria-label="`Tipo da refeição ${index+1}`"><option v-for="t in types" :key="t">{{t}}</option></select><label class="time"><Clock3 :size="14"/><input type="time" v-model="meal.time" :aria-label="`Horário da refeição ${index+1}`"/></label></div><strong class="meal-calories">{{summary.missing.calories?'≥ ':''}}{{format(summary.totals.calories,0)}} <small>kcal</small></strong><button class="icon-button remove-meal" @click="emit('remove')" aria-label="Remover refeição"><Trash2 :size="16"/></button></div>
  <div v-if="meal.items.length" class="meal-items">
    <div class="meal-item with-units" v-for="(item,i) in meal.items" :key="i">
      <div class="food-name"><strong>{{foods.get(item.food_id)?.name}}</strong><small>{{label(foods.get(item.food_id)?.category)}}</small></div>
      <div class="portion-controls">
        <div><small>Qtd. por unidade</small><PortionInput :item="item" :food="foods.get(item.food_id)" /></div>
        <span class="multiply-sign" aria-hidden="true">×</span>
        <label>Unidades <input :value="item.multiplier ?? 1" @input="item.multiplier=$event.target.value === '' ? '' : Number($event.target.value)" type="number" min="0" max="10000" step="0.1" required :aria-label="`Unidades de ${foods.get(item.food_id)?.name}`"/></label>
        <small class="consumed-quantity">Total: {{format(consumedQuantity(item,foods.get(item.food_id)))}} {{foods.get(item.food_id)?.unit}}</small>
      </div>
      <span class="item-calories">{{format(foods.get(item.food_id)?.calories==null?null:foods.get(item.food_id).calories*consumedQuantity(item,foods.get(item.food_id))/foods.get(item.food_id).base_quantity,0)}}<small> kcal</small></span>
      <button class="icon-button" @click="meal.items.splice(i,1)" :aria-label="`Remover ${foods.get(item.food_id)?.name}`"><XIcon/></button>
    </div>
  </div>
  <p v-else class="empty-meal">Esta refeição ainda não tem alimentos.</p>
  <div class="meal-footer"><button class="text-button" @click="emit('add')"><Plus :size="16"/> Adicionar alimento</button><div class="mini-nutrients"><span v-for="n in nutrients.slice(1,4)" :key="n.key"><i :style="{background:n.color}"></i>{{n.label}} <b>{{summary.missing[n.key]?'≥ ':''}}{{format(summary.totals[n.key])}} g</b></span></div></div>
  <details v-if="meal.items.length" class="meal-detail"><summary>Ver todos os nutrientes</summary><div class="table-scroll"><table><thead><tr><th>Alimento / base</th><th v-for="n in nutrients" :key="n.key">{{n.label}} ({{n.unit}})</th></tr></thead><tbody><tr v-for="(i,index) in meal.items" :key="index"><td>{{foods.get(i.food_id)?.name}}<small>{{format(foods.get(i.food_id)?.base_quantity)}} {{foods.get(i.food_id)?.unit}} / padrão</small></td><td v-for="n in nutrients" :key="n.key">{{format(foods.get(i.food_id)?.[n.key]==null?null:foods.get(i.food_id)[n.key]*consumedQuantity(i,foods.get(i.food_id))/foods.get(i.food_id).base_quantity)}}</td></tr><tr><th>Total</th><th v-for="n in nutrients" :key="n.key">{{summary.missing[n.key]?'≥ ':''}}{{format(summary.totals[n.key])}}</th></tr></tbody></table></div></details>
</section>
</template>
<script>
import {X as XIcon} from 'lucide-vue-next';
export default {components:{XIcon}};
</script>
