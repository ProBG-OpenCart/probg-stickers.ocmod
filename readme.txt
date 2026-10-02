Product card / product list example:

{% if product.stickers %}
  <div class="product-stickers product-stickers-{{ product.stickers[0].position }}">
    {% for sticker in product.stickers %}
      <span class="product-sticker sticker" style="background:{{ sticker.color }}; color:{{ sticker.text_color }};">
        {{ sticker.name }}
      </span>
    {% endfor %}
  </div>
{% endif %}

Product page example:

{% if stickers %}
  <div class="product-stickers product-stickers-{{ stickers[0].position }}">
    {% for sticker in stickers %}
      <span class="product-sticker sticker" style="background:{{ sticker.color }}; color:{{ sticker.text_color }};">
        {{ sticker.name }}
      </span>
    {% endfor %}
  </div>
{% endif %}

The nearest product image wrapper should use position: relative so the sticker container is positioned against the product image.
