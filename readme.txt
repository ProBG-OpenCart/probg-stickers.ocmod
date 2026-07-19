{% if product.stickers %}
  <div class="product-stickers-{{ sticker.position }}">
    {% for sticker in product.stickers %}
      <div class="product-sticker product-sticker-{{ sticker.position }}" style=" background:{{ sticker.color }}; color:{{ sticker.text_color }}; ">
        {{ sticker.name }}
      </div>
    {% endfor %}
  </div>
{% endif %}