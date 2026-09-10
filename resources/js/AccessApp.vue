<script setup>
import { ref, computed, onMounted } from 'vue';
import { api } from './api';
import nutriaLogo from '../images/nutria-logo.png';
const props = defineProps({
    user: Object,
    setup: Boolean,
    adminEmail: {
        type: String,
        default: '',
    },
});
const adminPath=location.pathname==='/admin';
const mode=ref(location.pathname==='/register'?'register':adminPath&&props.setup?'setup':'login');
const form = ref({
    name: '',
    email: props.setup ? props.adminEmail : '',
    crn: '',
    password: '',
    password_confirmation: '',
    token: '',
});
const error=ref(''),message=ref(''),busy=ref(false),users=ref([]),filter=ref('pending');
const isAdmin=computed(()=>adminPath&&props.user?.role==='admin');
const filtered=computed(()=>users.value.filter(u=>!filter.value||u.status===filter.value));
const statuses={pending:'Aguardando aprovação',approved:'Aprovado',rejected:'Não aprovado'};
async function submit(){busy.value=true;error.value='';try{const result=await api('auth/'+mode.value,'POST',form.value);if(result.redirect)location.assign(result.redirect);else{message.value=result.message;form.value.password='';form.value.password_confirmation='';}}catch(e){error.value=e.message;}finally{busy.value=false;}}
async function refresh(){users.value=await api('admin/users');}
async function update(user,status){busy.value=true;error.value='';try{await api('admin/users/'+user.id,'PATCH',{status});await refresh();message.value=status==='approved'?'Acesso liberado para '+user.name+'.':'Cadastro atualizado.';}catch(e){error.value=e.message;}finally{busy.value=false;}}
async function logout(){const result=await api('auth/logout','POST');location.assign(result.redirect);}
onMounted(async()=>{if(isAdmin.value){try{await refresh();}catch(e){error.value=e.message;}}});
</script>
<template>
<main class="access-shell" :class="{'admin-shell':isAdmin}">
  <header class="access-header"><span class="brand"><img class="brand-logo" :src="nutriaLogo" alt="" aria-hidden="true"/>nutria<span class="brand-dot">.</span></span><span>{{isAdmin?'ADMINISTRAÇÃO':'SEU ESPAÇO DE NUTRIÇÃO'}}</span></header>
  <div v-if="error" class="error-banner" role="alert">{{error}}</div><p v-if="message" class="access-notice" role="status">{{message}}</p>
  <template v-if="isAdmin">
    <div class="section-head"><div><h1>Olá, {{user.name}}</h1><p>Analise os cadastros para liberar o acesso dos nutricionistas.</p></div><div class="access-actions"><a class="button secondary" href="/">Meu espaço</a><button class="button secondary" @click="logout">Sair</button></div></div>
    <div class="admin-stats"><section class="panel" v-for="(label,key) in statuses" :key="key"><strong>{{users.filter(u=>u.status===key).length}}</strong><span>{{label}}</span></section></div>
    <section class="panel"><div class="section-head"><h2>Solicitações de cadastro</h2><select v-model="filter" aria-label="Filtrar situação"><option value="">Todos</option><option v-for="(label,key) in statuses" :value="key">{{label}}</option></select></div>
      <div class="approval-row" v-for="u in filtered" :key="u.id"><div><h3>{{u.name}}</h3><p>{{u.email}}</p><small>CRN: {{u.crn||'Não informado'}} · {{statuses[u.status]}}</small></div><div class="access-actions"><button v-if="u.status!=='approved'" class="button primary" :disabled="busy" @click="update(u,'approved')">Aprovar acesso</button><button v-if="u.status!=='rejected'" class="button secondary" :disabled="busy" @click="update(u,'rejected')">{{u.status==='approved'?'Bloquear acesso':'Não aprovar'}}</button></div></div><p v-if="!filtered.length" class="empty">Nenhum cadastro nesta situação.</p>
    </section>
  </template>
  <section v-else class="panel access-card">
    <p class="eyebrow">{{adminPath?'ACESSO DO ADMINISTRADOR':'BEM-VINDO'}}</p>
    <h1>{{mode==='register'?'Solicitar cadastro':mode==='setup'?'Defina sua primeira senha':'Entre no seu espaço'}}</h1>
    <p class="muted">{{mode==='register'?'Seu acesso será liberado após a aprovação do administrador.':mode==='setup'?'Use o código de ativação configurado para este ambiente para cadastrar sua senha.':'Acesse seus pacientes e organize seus planos alimentares.'}}</p>
    <form v-if="!message" @submit.prevent="submit" class="access-form">
      <label v-if="mode==='register'">Nome completo<input v-model="form.name" required maxlength="160" autocomplete="name" /></label>
      <label>E-mail<input v-model="form.email" type="email" required maxlength="255" autocomplete="username" :readonly="mode==='setup'" /></label>
      <label v-if="mode==='register'">CRN (opcional)<input v-model="form.crn" maxlength="50" /></label>
      <label v-if="mode==='setup'">Código de ativação<input v-model="form.token" type="password" required autocomplete="off" /><small>Use o valor configurado em NUTRIA_ADMIN_SETUP_TOKEN para o primeiro acesso.</small></label>
      <label>{{mode==='login'?'Senha':'Crie uma senha'}}<input v-model="form.password" type="password" required :minlength="mode==='login'?1:8" :autocomplete="mode==='login'?'current-password':'new-password'" /><small v-if="mode!=='login'">Use pelo menos 8 caracteres.</small></label>
      <label v-if="mode!=='login'">Confirme a senha<input v-model="form.password_confirmation" type="password" required minlength="8" autocomplete="new-password" /></label>
      <button class="button primary full" :disabled="busy">{{busy?'Aguarde…':mode==='register'?'Enviar para aprovação':mode==='setup'?'Definir senha e entrar':'Entrar'}}</button>
    </form>
    <p class="access-links"><a v-if="mode==='register'||message" href="/login">Voltar para o login</a><a v-else-if="!adminPath" href="/register">Ainda não tem acesso? Solicitar cadastro</a></p>
  </section>
</main>
</template>
<style scoped>
.access-shell{max-width:540px;margin:0 auto;padding:40px 20px;min-height:100vh}.admin-shell{max-width:1100px}.access-header{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:36px}.access-header .brand-logo{width:38px;height:38px}.access-header>span:last-child{font-size:.65rem;letter-spacing:.12em;color:#627363}.access-card{padding:32px}.access-card h1{font-size:1.8rem;margin:12px 0}.access-form{display:grid;gap:18px;margin-top:26px}.access-links{margin-top:22px;text-align:center}.access-links a{color:#285d49}.access-notice{background:#e6f1e6;color:#285d49;padding:18px;border-radius:12px}.admin-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:24px 0}.admin-stats section{display:grid;gap:8px}.admin-stats strong{font-size:2rem}.approval-row{display:flex;justify-content:space-between;align-items:center;gap:20px;padding:20px 0;border-bottom:1px solid #e4e9e2}.approval-row p{overflow-wrap:anywhere}.access-actions{display:flex;flex-wrap:wrap;gap:10px}.access-actions a{text-decoration:none}@media(max-width:600px){.admin-stats{grid-template-columns:1fr}.approval-row,.section-head{align-items:stretch;flex-direction:column}.access-card{padding:22px}.access-shell{padding:24px 16px}}
</style>
