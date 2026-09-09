<script setup>
import { computed } from 'vue';
import { consumedQuantity, measureOptions } from '../nutrition';
const props = defineProps({ item: Object, food: Object });
const liquid = computed(() => props.food?.unit === 'mL');
const measure = computed(() => props.item.measure && props.item.measure !== 'g' ? props.item.measure : 'mL');
const amount = computed(() => liquid.value && (!props.item.measure || props.item.measure === 'g') ? consumedQuantity({...props.item, multiplier: 1}, props.food) : props.item.quantity);
function setAmount(value) {
  props.item.quantity = value === '' ? '' : Number(value);
  if (liquid.value) props.item.measure = measure.value;
}
function changeMeasure(value) {
  const volume = consumedQuantity({...props.item, multiplier: 1}, props.food);
  props.item.measure = value;
  if (value === 'goblet') props.item.measure_ml ??= 150;
  props.item.quantity = volume / (value === 'goblet' ? props.item.measure_ml : measureOptions.find(m => m.key === value).ml);
}
</script>
<template>
  <span class="portion-input">
    <input :value="amount" @input="setAmount($event.target.value)" type="number" min="0" max="100000" step="any" :aria-label="`Quantidade de ${food?.name}`" />
    <select v-if="liquid" :value="measure" @change="changeMeasure($event.target.value)" :aria-label="`Medida de ${food?.name}`"><option v-for="m in measureOptions" :key="m.key" :value="m.key">{{m.label}}</option></select>
    <span v-else>{{food?.unit}}</span>
    <label v-if="liquid && measure==='goblet'">mL por taça<input :value="item.measure_ml ?? 150" @input="item.measure_ml=Number($event.target.value)" type="number" min="1" max="2000" step="any" aria-label="Volume da taça em mL" /></label>
  </span>
</template>
<style scoped>
.portion-input { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
.portion-input input { width:82px; min-height:40px; padding:7px; }
.portion-input select { width:auto; max-width:100%; min-height:40px; padding:7px; }
.portion-input label { display:flex; gap:6px; align-items:center; font-size:.75rem; }
@media print { select, input { border:0; background:none; appearance:none; } }
</style>
