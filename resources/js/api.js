export async function api(path,method='GET',body){
  const response=await fetch('/api/'+path,{method,headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},...(body?{body:JSON.stringify(body)}:{})});
  if(response.status===204) return null;
  const data=await response.json();
  if(!response.ok) throw new Error(data.errors?Object.values(data.errors).flat().join(' '):data.message||'Não foi possível concluir a operação.');
  return data;
}
