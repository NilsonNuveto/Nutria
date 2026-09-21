<script setup>
import {ref,computed} from 'vue';
import {api} from '../api';
const props=defineProps({patients:Array});
const emit=defineEmits(['plan','changed']);
const draft=ref(null),error=ref(''),busy=ref(false),search=ref('');
const filtered=computed(()=>props.patients.filter(p=>p.name.toLocaleLowerCase().includes(search.value.toLocaleLowerCase())));
function edit(patient){draft.value=patient?{...patient}:{name:'',email:'',phone:'',birth_date:'',notes:''};error.value='';}
async function save(){busy.value=true;error.value='';try{const p=draft.value;const saved=await api('patients'+(p.id?'/'+p.id:''),p.id?'PUT':'POST',p);draft.value=null;search.value='';emit('changed',{type:'upsert',patient:saved});}catch(e){error.value=e.message;}finally{busy.value=false;}}
async function remove(p){if(!confirm('Excluir o cadastro de '+p.name+'?'))return;try{await api('patients/'+p.id,'DELETE');emit('changed',{type:'remove',id:p.id});}catch(e){error.value=e.message;}}
</script>
<template>
<div><p v-if="error" class="error-banner" role="alert">{{error}}</p><div class="section-head patient-toolbar"><label>Buscar paciente<input v-model="search" placeholder="Nome do paciente" /></label><button class="button primary" @click="edit()">Cadastrar paciente</button></div>
<form v-if="draft" class="panel form-panel" @submit.prevent="save"><h2>{{draft.id?'Editar paciente':'Novo paciente'}}</h2><div class="form-grid"><label class="span-2">Nome completo<input v-model="draft.name" required maxlength="160" /></label><label>Nascimento<input v-model="draft.birth_date" type="date" /></label><label>Telefone<input v-model="draft.phone" maxlength="40" /></label><label class="span-2">E-mail<input v-model="draft.email" type="email" /></label><label class="span-2">Observações<textarea v-model="draft.notes" rows="3" maxlength="10000" /></label></div><div class="patient-actions"><button class="button primary" :disabled="busy">Salvar paciente</button><button type="button" class="button secondary" @click="draft=null">Cancelar</button></div></form>
<section class="patient-list"><article v-for="p in filtered" :key="p.id" class="panel"><h2>{{p.name}}</h2><p>{{p.email||'E-mail não informado'}}</p><p>{{p.phone||'Telefone não informado'}}</p><p v-if="p.notes" class="muted">{{p.notes}}</p><div class="patient-actions"><button class="button primary" @click="emit('plan',p)">Novo plano</button><button class="button secondary" @click="edit(p)">Editar</button><button class="text-button danger" @click="remove(p)">Excluir</button></div></article><p v-if="!filtered.length" class="empty">Nenhum paciente cadastrado. Comece adicionando seu primeiro paciente.</p></section></div>
</template>
<style scoped>.patient-toolbar{align-items:flex-end}.patient-toolbar label{flex:1;min-width:0;max-width:320px}.patient-toolbar input{width:100%;min-height:43px}.patient-toolbar>.button{flex-shrink:0}.patient-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:18px;margin-top:24px}.patient-list p{overflow-wrap:anywhere}.patient-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px}</style>
