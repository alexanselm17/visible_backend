import openai
import sys
import os
import base64

# Get API key from environment variables for security
openai.api_key = "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"

advert_path = sys.argv[1]
screenshot_path = sys.argv[2]

def encode_image(image_path):
    with open(image_path, "rb") as image_file:
        return base64.b64encode(image_file.read()).decode('utf-8')

advert_base64 = encode_image(advert_path)
screenshot_base64 = encode_image(screenshot_path)

prompt = """
You are verifying that a WhatsApp Status screenshot contains a specific image.

Compare the uploaded WhatsApp Status screenshot with the original image.

1. Confirm the WhatsApp interface shows "My status" and "Just now" or a recent timestamp.
2. Confirm the status image shown matches the original image in design and content.

Return only: "✅ Verified: The image was successfully posted." or "❌ Not Verified", followed by a brief reason.
"""

response = openai.chat.completions.create(
    model="gpt-4o",
    messages=[
        {
            "role": "user",
            "content": [
                {"type": "text", "text": prompt},
                {
                    "type": "image_url",
                    "image_url": {
                        "url": f"data:image/jpeg;base64,{advert_base64}"
                    }
                },
                {
                    "type": "image_url",
                    "image_url": {
                        "url": f"data:image/jpeg;base64,{screenshot_base64}"
                    }
                }
            ]
        }
    ],
    max_tokens=300,
)

print(response.choices[0].message.content)