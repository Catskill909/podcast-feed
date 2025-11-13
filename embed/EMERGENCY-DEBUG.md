# EMERGENCY DEBUG - STEP BACK AND THINK

## What We Know FOR SURE

1. **Desktop preview (left):** Episode images appear LARGER
2. **Tablet preview (middle):** Episode images appear SMALL (same as mobile)
3. **Mobile preview (right):** Episode images appear SMALL

## What The CSS Says

```css
/* Base - Desktop */
.episode-image {
    width: 100px;
    height: 100px;
    min-width: 100px;
}

/* Tablet - 481px to 769px */
@media (min-width: 481px) and (max-width: 769px) {
    .episode-image {
        width: 70px;
        height: 70px;
        min-width: 70px;
    }
}

/* Mobile - 480px and below */
@media (max-width: 480px) {
    .episode-image {
        width: 50px;
        height: 50px;
        min-width: 50px;
    }
}
```

## The Question

**If tablet shows 50px images, that means the MOBILE query is matching!**

**Why would the mobile query match at 768px?**

Possible reasons:
1. The viewport is <480px (but how?)
2. The mobile query comes AFTER tablet query and overrides it
3. There's another rule we haven't found
4. The CSS file isn't loading at all

## Let Me Check The Order

Mobile query is at line 2242 (inside @media max-width: 480px)
Tablet query is at line 2091 (inside @media min-width: 481px and max-width: 769px)

**Tablet comes BEFORE mobile in the file - that's correct**

## Wait - Let Me Check Something

The mobile query is `@media (max-width: 480px)` - line 2101
But there might be ANOTHER mobile query!

Let me search for ALL media queries that affect episode-image...
