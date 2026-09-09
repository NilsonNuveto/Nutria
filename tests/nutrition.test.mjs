import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import {total,profile} from '../resources/js/nutrition.js';
const seed=JSON.parse(fs.readFileSync(new URL('../database/imports/seed.json',import.meta.url)));
const foods=new Map(seed.foods.map(f=>[f.id,f]));

test('liquid measures convert nutrients and recipe mass without changing legacy grams',()=>{
  const food={id:9999,unit:'mL',base_quantity:100,calories:61.8,raw_values:{volume_conversion:{density_g_ml:1.03}}};
  const liquids=new Map([[food.id,food]]);
  const item={food_id:food.id,quantity:1,measure:'glass',multiplier:2};
  const result=total([item],liquids);
  assert.ok(Math.abs(result.totals.calories-296.64)<1e-8);
  assert.ok(Math.abs(result.quantity-494.4)<1e-8);
  assert.ok(Math.abs(total([{food_id:food.id,quantity:100}],liquids).totals.calories-60)<1e-8);
  assert.ok(Math.abs(total([{...item,measure:'goblet',measure_ml:180}],liquids).quantity-370.8)<1e-8);
});
test('meal units scale all nutrients and preserve legacy items',()=>{
  const food=seed.foods.find(f=>f.source_id===11);
  const original={food_id:food.id,quantity:100};
  assert.deepEqual(total([original],foods),total([{...original,multiplier:1}],foods));
  assert.deepEqual(total([original],foods),total([{...original,quantity:50,multiplier:2}],foods));
  assert.deepEqual(total([{...original,quantity:25}],foods),total([{...original,quantity:50,multiplier:0.5}],foods));
  assert.deepEqual(total([],foods),total([{...original,multiplier:0}],foods));
});
test('original profile and calorie adjustment',()=>{const p=profile(seed.profile,seed.activities);assert.ok(Math.abs(p.bmr-1371.7)<1e-8);assert.ok(Math.abs(p.target-2126.135)<1e-8);assert.ok(Math.abs(profile({...seed.profile,adjustment:500},seed.activities).target-1626.135)<1e-8);});
test('meal totals independently reconcile to original source rows',()=>{const items=seed.meals.flatMap(m=>m.items),result=total(items,foods);for(const n of ['calories','protein','carbs','fat','fiber','sodium']){let sum=0;for(const item of items){const food=foods.get(item.food_id);sum+=food[n]/food.base_quantity*item.quantity;}assert.ok(Math.abs(sum-result.totals[n])<1e-8);}assert.equal(items.length,15);assert.equal(result.quantity,1436);});
test('no items, zeros, and unknown data are distinct',()=>{assert.equal(total([],foods).totals.calories,0);const f=seed.foods.find(f=>f.sodium===null);assert.equal(total([{food_id:f.id,quantity:100}],foods).missing.sodium,1);assert.equal(total([{food_id:f.id,quantity:0}],foods).missing.sodium,0);});
