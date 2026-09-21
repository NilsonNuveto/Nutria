<script setup>
import { ref, computed } from 'vue';
import { Search, Plus, X } from 'lucide-vue-next';
import { format,label } from '../nutrition';
import { createFoodSearch } from '../foodSearch';
const props=defineProps({foods:Array,sources:Array,alternative:Boolean});
const emit=defineEmits(['add','close']);
const query=ref(''),quantity=ref(100);
const matchesFoodSearch=computed(()=>createFoodSearch(query.value));
const filtered=computed(()=>props.foods.filter(f=>matchesFoodSearch.value(f.name)));
function add(food){if(Number(quantity.value)>0)emit('add',{food_id:food.id,quantity:Number(quantity.value),...(food.unit==='mL'?{measure:'mL'}:{})});}
</script>
<template>
  <div class="picker">
    <div class="section-head"><div><span class="eyebrow">CATÁLOGO NUTRICIONAL</span><h2>{{alternative?'Adicionar opção “ou”':'Adicionar alimento'}}</h2></div><button class="icon-button" @click="emit('close')" aria-label="Fechar"><X :size="22"/></button></div>
    <label class="search"><Search :size="19"/><input autofocus v-model="query" placeholder="Buscar alimento, preparo ou ingrediente" aria-label="Buscar alimento"/></label>
    <label class="quantity-field">Quantidade na unidade indicada em cada alimento<input v-model.number="quantity" type="number" min="0.1" max="100000" step="0.1"/></label>
    <div class="picker-results"><button class="food-result" v-for="f in filtered.slice(0,100)" :key="f.id" @click="add(f)" :disabled="quantity<=0"><span><strong>{{f.name}}</strong><small>{{label(f.category)}} · {{sources.find(s=>s.id===f.source_id)?.name||'Cadastro próprio'}} · {{format(f.base_quantity)}} {{f.unit}}</small></span><span class="nowrap">{{format(f.calories,0)}} kcal <Plus :size="17"/></span></button><p v-if="!filtered.length" class="empty">Nenhum alimento encontrado. Tente outro termo.</p><p v-if="filtered.length>100" class="muted">Exibindo os primeiros 100. Refine a busca para encontrar outros alimentos.</p></div>
  </div>
</template>
