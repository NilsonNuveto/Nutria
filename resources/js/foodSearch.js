function normalizeSearchText(value) {
  return String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^\p{L}\p{N}]+/gu, ' ')
    .trim();
}

export function createFoodSearch(query) {
  const words = normalizeSearchText(query).split(/\s+/).filter(Boolean);

  return (name) => {
    const normalizedName = normalizeSearchText(name);
    return words.every(word => normalizedName.includes(word));
  };
}
