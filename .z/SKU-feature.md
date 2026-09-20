I'm happy to dive deep into the SKU (Stock Keeping Unit) so you have a complete picture. You are right to ask these questions—understanding why we build a feature is just as important as how we build it.

Here is everything you need to know about SKUs, the business cases for them, and the technical complexity.

1. What type of business needs an SKU?
The SKU is absolutely critical for businesses that sell products with many variations, or products that do not come with factory barcodes.

Clothing / Fashion: A shirt comes in 5 colors and 4 sizes (20 variants). An SKU like SHRT-RED-MED allows warehouse workers to instantly identify the exact item on a stock report.
Electronics & Parts: IPH-15-PRO-256-BLK is much easier for a manager to read on an inventory report than "Apple iPhone 15 Pro, 256GB, Black".
Furniture / Handmade Goods: Items built by the store itself.
2. When can we ignore the SKU?
We can ignore the SKU for Supermarkets, Pharmacies, and Convenience Stores. In these businesses, 99% of products (like a can of Pepsi or a box of Panadol) arrive with a global factory barcode. The store owner doesn't care about an internal SKU; they just scan the barcode and sell it.

However, since Markt POS is a premium, multi-purpose POS, we must support both types of businesses. Therefore, the SKU field must exist, but we keep it nullable (optional) so supermarkets can just leave it empty.

3. Is implementing an SKU complex?
No, it is incredibly simple.

Database: It is literally just a string column (sku) on the product_variants table.
Logic: There is no complex math or relationships. It is just a text field.
POS Search: We just tell the POS search bar: "Search by Name, Barcode, OR SKU."
Auto-generation (Optional): Many premium systems auto-generate the SKU based on the name and attributes (e.g., typing "T-Shirt", "Red", "Large" automatically suggests the SKU TSH-RED-LRG). This is a nice-to-have UI feature, but not strictly necessary for v1.
4. Will we need some sort of printing feature for SKUs?
Yes, absolutely. Businesses that use SKUs rely heavily on Label Printing. Since these items (like handmade shirts) don't have factory barcodes, the store administrator will need a "Print Labels" page in the Filament admin panel.

The system will generate a PDF containing small sticker designs (e.g., 3cm x 5cm) that the store prints on a thermal sticker printer. A professional label usually contains:

Store Name (Top)
Product Name (Middle)
SKU: TSH-RED-LRG (Printed as readable text so workers can identify it)
Internal Barcode: Printed as the actual scannable black-and-white lines (using the product_barcodes table we discussed earlier) so the cashier can scan it.
Price: $15.00
Summary Implementing the sku column is very easy (just one nullable string column), but it unlocks the entire fashion, electronics, and specialized retail market for Markt POS.

Based on this, how would you like me to update the implementation_plan.md regarding SKUs and potentially the Label Printing feature?